<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RedirectIfEmailMfaRequired;
use App\Actions\Fortify\RedirectIfWhatsappMfaRequired;
use App\Actions\Fortify\RedirectIfPasswordResetRequired;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\User;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Socialite;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
            'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
            'csrfToken' => csrf_token(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn (Request $request) => Inertia::render('auth/TwoFactorChallenge', [
            'csrfToken' => csrf_token(),
            'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('email-mfa', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('email_mfa.id'));
        });

        RateLimiter::for('whatsapp-mfa', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('whatsapp_mfa.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Configure custom authentication logic.
     */
    private function configureAuthentication(): void
    {
        Fortify::loginThrough(function () {
            return [
                config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                Features::enabled(Features::twoFactorAuthentication()) ? RedirectsIfTwoFactorAuthenticatable::class : null,
                RedirectIfPasswordResetRequired::class,
                RedirectIfWhatsappMfaRequired::class,
                RedirectIfEmailMfaRequired::class,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ];
        });

        Fortify::authenticateUsing(function (Request $request) {
            session([
                'url.intended' => session()->get('url.intended') ?? route('dashboard'),
            ]);

            $ldapUser = $this->ldapAuthenticate($request->input(Fortify::username()), $request->password);
            if ($ldapUser instanceof User) {
                if ($ldapUser->isLocked() || ! $ldapUser->active) {
                    return null;
                }
                $ldapUser->update(['failed_login_attempts' => 0]);

                return $ldapUser;
            }

            $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

            if (! $user) {
                return null;
            }

            if ($user->isLocked() || ! $user->active) {
                return null;
            }

            if (Hash::check($request->password, $user->password)) {
                $user->update(['failed_login_attempts' => 0]);

                return $user;
            }

            $attempts = $user->failed_login_attempts + 1;
            $user->update([
                'failed_login_attempts' => $attempts,
                'locked_at' => $attempts >= 3 ? now() : null,
            ]);

            return null;
        });
    }

    /**
     * Handle LDAP authentication logic.
     */
    private function ldapAuthenticate(string $username, string $password): User|false
    {
        $connection = ldap_connect(config('app.ldap.host'), config('app.ldap.port'));
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        if (! config('app.ldap.enabled') || ! $connection) {
            return false;
        }

        if (! @ldap_bind($connection, config('app.ldap.dn'), config('app.ldap.pass'))) {
            return false;
        }

        if (! str_contains($username, '@pupukkaltim.com')) {
            $username .= '@pupukkaltim.com';
        }

        $result = @ldap_search(
            $connection,
            config('app.ldap.tree'),
            "(mail={$username})",
            ['displayname', 'mail', 'uid', 'ou', 'sn', 'givenname']
        );

        $entry = @ldap_first_entry($connection, $result);

        if (! $entry) {
            return false;
        }

        $userDn = @ldap_get_dn($connection, $entry);

        if (! $userDn || ! @ldap_bind($connection, $userDn, $password)) {
            return false;
        }

        $attrs = @ldap_get_attributes($connection, $entry);
        $aliases = array_map(
            fn (string $mail) => Str::before($mail, '@pupukkaltim.com'),
            array_filter($attrs['mail'], fn ($key) => $key !== 'count', ARRAY_FILTER_USE_KEY)
        );

        return User::whereIn('username', $aliases)->first() ?? false;
    }
}
