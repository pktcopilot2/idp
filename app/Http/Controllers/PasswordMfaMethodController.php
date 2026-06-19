<?php

namespace App\Http\Controllers;

use App\Features\EmailMfa;
use App\Features\TwoFactorAuthentication as TwoFactorAuthenticationFeature;
use App\Features\WhatsAppMfa;
use App\Models\User;
use App\Notifications\EmailMfaCode;
use App\Services\WhatsappOtpSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class PasswordMfaMethodController extends Controller
{
    public function __construct(
        private readonly WhatsappOtpSender $whatsappOtpSender,
    ) {}

    /**
     * Show the MFA method selection page.
     */
    public function selectMethod(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get('password_mfa.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || $user->isLocked() || ! $user->active) {
            $request->session()->forget(['password_mfa.id', 'password_mfa.remember']);

            return redirect()->route('login');
        }

        $methods = $this->getEnabledMethods($user);

        if (empty($methods)) {
            $request->session()->forget(['password_mfa.id', 'password_mfa.remember']);

            return redirect()->route('login');
        }

        if (count($methods) === 1) {
            return $this->redirectToMethod($user, $methods[0]['key'], (bool) $request->session()->get('password_mfa.remember', false));
        }

        return Inertia::render('auth/PasswordMfaMethodSelect', [
            'methods' => $methods,
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * Handle the method choice and initiate the selected MFA flow.
     */
    public function chooseMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'method' => ['required', 'string', 'in:totp,email,whatsapp'],
        ]);

        $userId = $request->session()->get('password_mfa.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || $user->isLocked() || ! $user->active) {
            $request->session()->forget(['password_mfa.id', 'password_mfa.remember']);

            return redirect()->route('login');
        }

        $enabledKeys = array_column($this->getEnabledMethods($user), 'key');

        if (! in_array($request->input('method'), $enabledKeys, true)) {
            return redirect()->route('password-mfa.method.select');
        }

        return $this->redirectToMethod($user, $request->string('method')->toString(), (bool) $request->session()->get('password_mfa.remember', false));
    }

    // -----------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------

    private function redirectToMethod(User $user, string $method, bool $remember): RedirectResponse
    {
        return match ($method) {
            'totp'     => $this->redirectToTotp($user, $remember),
            'email'    => $this->redirectToEmail($user, $remember),
            'whatsapp' => $this->redirectToWhatsapp($user, $remember),
            default    => redirect()->route('login'),
        };
    }

    private function redirectToTotp(User $user, bool $remember): RedirectResponse
    {
        session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $remember,
        ]);

        session()->forget(['password_mfa.id', 'password_mfa.remember']);

        return redirect()->route('two-factor.login');
    }

    private function redirectToEmail(User $user, bool $remember): RedirectResponse
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("email_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        session()->put([
            'email_mfa.id' => $user->getKey(),
            'email_mfa.remember' => $remember,
        ]);

        session()->forget(['password_mfa.id', 'password_mfa.remember']);

        $user->notify(new EmailMfaCode($code));

        return redirect()->route('email-mfa.create');
    }

    private function redirectToWhatsapp(User $user, bool $remember): RedirectResponse
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("whatsapp_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        session()->put([
            'whatsapp_mfa.id' => $user->getKey(),
            'whatsapp_mfa.remember' => $remember,
        ]);

        session()->forget(['password_mfa.id', 'password_mfa.remember']);

        $this->whatsappOtpSender->send($user, $code);

        return redirect()->route('whatsapp-mfa.create');
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function getEnabledMethods(User $user): array
    {
        $methods = [];

        if (
            Feature::for(null)->active(TwoFactorAuthenticationFeature::class)
            && $user->two_factor_secret
            && ! is_null($user->two_factor_confirmed_at)
        ) {
            $methods[] = ['key' => 'totp', 'label' => 'Authenticator App (TOTP)'];
        }

        if (
            Feature::for(null)->active(WhatsAppMfa::class)
            && $user->whatsapp_mfa_enabled
            && $user->whatsapp_number
        ) {
            $methods[] = ['key' => 'whatsapp', 'label' => 'WhatsApp ('.$this->maskPhone((string) $user->whatsapp_number).')'];
        }

        if (
            Feature::for(null)->active(EmailMfa::class)
            && $user->email_mfa_enabled
            && $user->email
        ) {
            $methods[] = ['key' => 'email', 'label' => 'Email ('.$this->maskEmail((string) $user->email).')'];
        }

        return $methods;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 2));

        return $masked.'@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4).substr($phone, -4);
    }
}
