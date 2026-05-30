<?php

namespace App\Http\Controllers\Settings;

use App\Features\EmailMfa;
use App\Features\TwoFactorAuthentication as TwoFactorAuthenticationFeature;
use App\Features\WhatsAppMfa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Notifications\EmailMfaCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Pennant\Feature;

class SecurityController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::canManageTwoFactorAuthentication()
            && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                ? [new Middleware('password.confirm', only: ['edit'])]
                : [];
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication()
                && Feature::for(Auth::user())->active(TwoFactorAuthenticationFeature::class),
            'emailMfaFeatureEnabled' => Feature::for(Auth::user())->active(EmailMfa::class),
            'whatsappMfaFeatureEnabled' => Feature::for(Auth::user())->active(WhatsAppMfa::class),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'emailMfaEnabled' => (bool) $request->user()->email_mfa_enabled,
            'emailMfaSetupPending' => $request->session()->get('email_mfa_setup.id') === $request->user()->getKey(),
            'whatsappMfaEnabled' => (bool) $request->user()->whatsapp_mfa_enabled,
            'whatsappNumber' => $request->user()->whatsapp_number,
        ];

        if (Features::canManageTwoFactorAuthentication()
            && Feature::for(Auth::user())->active(TwoFactorAuthenticationFeature::class)) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Send a verification code to confirm email MFA setup.
     */
    public function initiateEmailMfa(Request $request): RedirectResponse
    {
        abort_unless(Feature::for(Auth::user())->active(EmailMfa::class), 403);

        $user = $request->user();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("email_mfa_setup:{$user->getKey()}", $code, now()->addMinutes(10));
        $request->session()->put('email_mfa_setup.id', $user->getKey());

        $user->notify(new EmailMfaCode($code));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A verification code has been sent to your email.')]);

        return back();
    }

    /**
     * Confirm the verification code and enable email MFA.
     */
    public function enableEmailMfa(Request $request): RedirectResponse
    {
        abort_unless(Feature::for(Auth::user())->active(EmailMfa::class), 403);

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $user = $request->user();
        $sessionId = $request->session()->get('email_mfa_setup.id');

        if ($sessionId !== $user->getKey()) {
            return redirect()->route('security.edit');
        }

        $cachedCode = Cache::get("email_mfa_setup:{$user->getKey()}");

        if (! $cachedCode || ! hash_equals($cachedCode, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => [__('The provided verification code is invalid.')],
            ]);
        }

        Cache::forget("email_mfa_setup:{$user->getKey()}");
        $request->session()->forget('email_mfa_setup.id');

        $user->update(['email_mfa_enabled' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email MFA enabled.')]);

        return back();
    }

    /**
     * Disable email MFA for the user.
     */
    public function disableEmailMfa(Request $request): RedirectResponse
    {
        abort_unless(Feature::for(Auth::user())->active(EmailMfa::class), 403);

        $request->user()->update(['email_mfa_enabled' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email MFA disabled.')]);

        return back();
    }

    /**
     * Enable WhatsApp MFA for the user.
     */
    public function enableWhatsappMfa(Request $request): RedirectResponse
    {
        abort_unless(Feature::for(Auth::user())->active(WhatsAppMfa::class), 403);

        $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:30'],
        ]);

        $request->user()->update([
            'whatsapp_mfa_enabled' => true,
            'whatsapp_number' => $request->whatsapp_number,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp MFA enabled.')]);

        return back();
    }

    /**
     * Disable WhatsApp MFA for the user.
     */
    public function disableWhatsappMfa(Request $request): RedirectResponse
    {
        abort_unless(Feature::for(Auth::user())->active(WhatsAppMfa::class), 403);

        $request->user()->update(['whatsapp_mfa_enabled' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp MFA disabled.')]);

        return back();
    }
}
