<?php

namespace App\Actions\Fortify;

use App\Features\EmailMfa;
use App\Features\TwoFactorAuthentication as TwoFactorAuthenticationFeature;
use App\Features\WhatsAppMfa;
use App\Helpers\LdapHelper;
use App\Models\User;
use App\Notifications\EmailMfaCode;
use App\Services\WhatsappOtpSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Fortify;
use Laravel\Pennant\Feature;

class RedirectIfMfaRequired
{
    public function __construct(
        private readonly WhatsappOtpSender $whatsappOtpSender,
    ) {}

    public function handle(Request $request, $next)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            return $next($request);
        }

        $methods = $this->getEnabledMethods($user);

        if (empty($methods)) {
            return $next($request);
        }

        $remember = $request->boolean('remember');

        if (count($methods) === 1) {
            return $this->redirectToMethod($user, $methods[0]['key'], $remember);
        }

        // Multiple MFA methods — let the user choose.
        $request->session()->put([
            'password_mfa.id' => $user->getKey(),
            'password_mfa.remember' => $remember,
        ]);

        return redirect()->route('password-mfa.method.select');
    }

    // -----------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------

    private function redirectToMethod(User $user, string $method, bool $remember): mixed
    {
        return match ($method) {
            'totp'     => $this->redirectToTotp($user, $remember),
            'email'    => $this->redirectToEmail($user, $remember),
            'whatsapp' => $this->redirectToWhatsapp($user, $remember),
            default    => redirect()->route('login'),
        };
    }

    private function redirectToTotp(User $user, bool $remember): mixed
    {
        session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $remember,
        ]);

        return redirect()->route('two-factor.login');
    }

    private function redirectToEmail(User $user, bool $remember): mixed
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("email_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        session()->put([
            'email_mfa.id' => $user->getKey(),
            'email_mfa.remember' => $remember,
        ]);

        $user->notify(new EmailMfaCode($code));

        return redirect()->route('email-mfa.create');
    }

    private function redirectToWhatsapp(User $user, bool $remember): mixed
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("whatsapp_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        session()->put([
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
