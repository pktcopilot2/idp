<?php

namespace App\Http\Controllers;

use App\Features\EmailMfa;
use App\Features\TwoFactorAuthentication as TwoFactorAuthenticationFeature;
use App\Features\WhatsAppMfa;
use App\Helpers\LdapHelper;
use App\Models\User;
use App\Notifications\EmailMfaCode;
use App\Services\WhatsappOtpSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Pennant\Feature;

class PasswordlessLoginController extends Controller
{
    public function __construct(
        private readonly WhatsappOtpSender $whatsappOtpSender,
        private readonly LoginRateLimiter $limiter,
    ) {}

    /**
     * Handle username submission and resolve available MFA methods.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            Fortify::username() => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')
                ->withErrors($validator)
                ->withInput($request->only(Fortify::username(), 'remember'))
                ->with('loginMode', 'passwordless');
        }

        $user = $this->resolveUser($request);

        if (! $user || $user->isLocked() || ! $user->active) {
            $this->limiter->increment($request);

            return redirect()->route('login')
                ->withErrors([Fortify::username() => trans('auth.failed')])
                ->withInput($request->only(Fortify::username(), 'remember'))
                ->with('loginMode', 'passwordless');
        }

        if ($user->is_need_password_reset) {
            $this->limiter->increment($request);

            return redirect()->route('login')
                ->withErrors([Fortify::username() => __('This account requires a password reset and cannot use passwordless login.')])
                ->withInput($request->only(Fortify::username(), 'remember'))
                ->with('loginMode', 'passwordless');
        }

        $methods = $this->getEnabledMethods($user);

        if (empty($methods)) {
            $this->limiter->increment($request);

            return redirect()->route('login')
                ->withErrors([Fortify::username() => __('No MFA method is enabled for this account.')])
                ->withInput($request->only(Fortify::username(), 'remember'))
                ->with('loginMode', 'passwordless');
        }

        $request->session()->put([
            'passwordless_login.id' => $user->getKey(),
            'passwordless_login.remember' => $request->boolean('remember'),
        ]);

        if (count($methods) === 1) {
            return $this->redirectToMethod($request, $user, $methods[0]['key']);
        }

        return redirect()->route('passwordless.method.select');
    }

    /**
     * Show method selection page.
     */
    public function selectMethod(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get('passwordless_login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || $user->isLocked() || ! $user->active) {
            $request->session()->forget(['passwordless_login.id', 'passwordless_login.remember']);

            return redirect()->route('login');
        }

        $methods = $this->getEnabledMethods($user);

        if (empty($methods)) {
            $request->session()->forget(['passwordless_login.id', 'passwordless_login.remember']);

            return redirect()->route('login');
        }

        if (count($methods) === 1) {
            return $this->redirectToMethod($request, $user, $methods[0]['key']);
        }

        return Inertia::render('auth/PasswordlessMethodSelect', [
            'methods' => $methods,
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * Handle method choice and initiate the selected MFA flow.
     */
    public function chooseMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'method' => ['required', 'string', 'in:totp,email,whatsapp'],
        ]);

        $userId = $request->session()->get('passwordless_login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || $user->isLocked() || ! $user->active) {
            $request->session()->forget(['passwordless_login.id', 'passwordless_login.remember']);

            return redirect()->route('login');
        }

        // Verify the chosen method is actually enabled for this user.
        $enabledKeys = array_column($this->getEnabledMethods($user), 'key');

        if (! in_array($request->input('method'), $enabledKeys, true)) {
            return redirect()->route('passwordless.method.select');
        }

        return $this->redirectToMethod($request, $user, $request->string('method')->toString());
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function redirectToMethod(Request $request, User $user, string $method): RedirectResponse
    {
        $remember = (bool) $request->session()->get('passwordless_login.remember', false);

        return match ($method) {
            'totp'     => $this->redirectToTotp($request, $user, $remember),
            'email'    => $this->redirectToEmail($request, $user, $remember),
            'whatsapp' => $this->redirectToWhatsapp($request, $user, $remember),
            default    => redirect()->route('login'),
        };
    }

    private function redirectToTotp(Request $request, User $user, bool $remember): RedirectResponse
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $remember,
        ]);

        return redirect()->route('two-factor.login');
    }

    private function redirectToEmail(Request $request, User $user, bool $remember): RedirectResponse
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("email_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        $request->session()->put([
            'email_mfa.id' => $user->getKey(),
            'email_mfa.remember' => $remember,
        ]);

        $user->notify(new EmailMfaCode($code));

        return redirect()->route('email-mfa.create');
    }

    private function redirectToWhatsapp(Request $request, User $user, bool $remember): RedirectResponse
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("whatsapp_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        $request->session()->put([
            'whatsapp_mfa.id' => $user->getKey(),
            'whatsapp_mfa.remember' => $remember,
        ]);

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

    private function resolveUser(Request $request): ?User
    {
        $aliases = LdapHelper::getUserAliases($request->input(Fortify::username()));

        if ($aliases === []) {
            return User::where(Fortify::username(), $request->input(Fortify::username()))->first();
        }

        return User::whereIn(Fortify::username(), $aliases)->first();
    }
}
