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
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Pennant\Feature;

class RedirectIfPasswordlessAuthenticationRequired
{
    public function __construct(
        private readonly WhatsappOtpSender $whatsappOtpSender,
        private readonly LoginRateLimiter $limiter,
    )
    {
    }

    public function handle(Request $request, $next)
    {
        if (config('authentication.mode') !== 'passwordless') {
            return $next($request);
        }

        $user = $this->resolveUser($request);

        if (! $user || $user->isLocked() || ! $user->active) {
            $this->throwFailedAuthenticationException($request);
        }

        if ($user->is_need_password_reset) {
            $this->limiter->increment($request);

            throw ValidationException::withMessages([
                Fortify::username() => [__('This account requires a password reset and cannot use passwordless login.')],
            ]);
        }

        if (
            Feature::for(null)->active(TwoFactorAuthenticationFeature::class)
            && $user->two_factor_secret
            && ! is_null($user->two_factor_confirmed_at)
        ) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.login');
        }

        if (
            Feature::for(null)->active(WhatsAppMfa::class)
            && $user->whatsapp_mfa_enabled
            && $user->whatsapp_number
        ) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            Cache::put("whatsapp_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

            $request->session()->put([
                'whatsapp_mfa.id' => $user->getKey(),
                'whatsapp_mfa.remember' => $request->boolean('remember'),
            ]);

            $this->whatsappOtpSender->send($user, $code);

            return redirect()->route('whatsapp-mfa.create');
        }

        if (
            Feature::for(null)->active(EmailMfa::class)
            && $user->email_mfa_enabled
            && $user->email
        ) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            Cache::put("email_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

            $request->session()->put([
                'email_mfa.id' => $user->getKey(),
                'email_mfa.remember' => $request->boolean('remember'),
            ]);

            $user->notify(new EmailMfaCode($code));

            return redirect()->route('email-mfa.create');
        }

        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [__('No passwordless authentication method is enabled for this account.')],
        ]);
    }

    private function resolveUser(Request $request): ?User
    {
        $aliases = LdapHelper::getUserAliases($request->input(Fortify::username()));

        if ($aliases === []) {
            return User::where(Fortify::username(), $request->input(Fortify::username()))->first();
        }

        return User::whereIn(Fortify::username(), $aliases)->first();
    }

    private function throwFailedAuthenticationException(Request $request): never
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [trans('auth.failed')],
        ]);
    }
}
