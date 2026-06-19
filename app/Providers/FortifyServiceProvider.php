<?php

namespace App\Providers;

use App\Actions\Fortify\Authenticate;
use App\Actions\Fortify\ConfirmPassword;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RedirectIfEmailMfaRequired;
use App\Actions\Fortify\RedirectIfMfaRequired;
use App\Actions\Fortify\RedirectIfPasswordlessAuthenticationRequired;
use App\Actions\Fortify\RedirectIfPasswordResetRequired;
use App\Actions\Fortify\RedirectIfWhatsappMfaRequired;
use App\Actions\Fortify\ResetUserPassword;
use App\Features\EmailMfa;
use App\Features\TwoFactorAuthentication as TwoFactorAuthenticationFeature;
use App\Features\WhatsAppMfa;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Requests\Auth\LoginRequest as AppLoginRequest;
use App\Models\User;
use App\Models\Passport\Client as OauthClient;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Pennant\Feature;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyLoginRequest::class, AppLoginRequest::class);
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
        $this->configurePasskeyAuthorization();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::authenticateUsing(app(Authenticate::class));
        Fortify::confirmPasswordsUsing(app(ConfirmPassword::class));
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $clientId = (string) $request->query('client_id', '');

            if ($clientId === '') {
                $intended = (string) $request->session()->get('url.intended', '');
                if ($intended !== '') {
                    $intendedPath = parse_url($intended, PHP_URL_PATH) ?: '';
                    if ($intendedPath === '/oauth/authorize') {
                        parse_str(parse_url($intended, PHP_URL_QUERY) ?: '', $query);
                        $clientId = (string) ($query['client_id'] ?? '');
                    }
                }
            }

            $oauthClient = null;
            if ($clientId !== '') {
                $client = OauthClient::query()
                    ->select(['id', 'name'])
                    ->find($clientId);

                if ($client) {
                    $oauthClient = [
                        'id' => (string) $client->id,
                        'name' => (string) $client->name,
                    ];
                }
            }

            return Inertia::render('auth/Login', [
                'authenticationMode' => config('authentication.mode'),
                'usesPasswordAuthentication' => config('authentication.mode') === 'password',
                'loginMode' => $request->session()->get('loginMode', config('authentication.mode')),
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister' => Features::enabled(Features::registration()),
                'message' => $request->session()->get('message'),
                'status' => $request->session()->get('status'),
                'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
                'csrfToken' => csrf_token(),
                'oauthClient' => $oauthClient,
            ]);
        });

        Fortify::twoFactorChallengeView(function (Request $request) {
            if (! Feature::for(null)->active(TwoFactorAuthenticationFeature::class)) {
                return redirect()->route('login');
            }

            return Inertia::render('auth/TwoFactorChallenge', [
                'csrfToken' => csrf_token(),
                'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
            ]);
        });

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
        Fortify::loginThrough(function (Request $request) {
            if (config('authentication.mode') === 'passwordless') {
                return [
                    config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                    config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                    RedirectIfPasswordlessAuthenticationRequired::class,
                ];
            }

            return [
                config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                RedirectIfPasswordResetRequired::class,
                RedirectIfMfaRequired::class,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ];
        });
    }

    /**
     * Configure passkey login authorization.
     */
    private function configurePasskeyAuthorization(): void
    {
        Passkeys::authorizeLoginUsing(function (Request $request, User $user, Passkey $passkey): bool {
            if ($user->isLocked()) {
                throw ValidationException::withMessages([
                    'credential' => __('This account has been locked.'),
                ]);
            }

            if (! $user->active) {
                throw ValidationException::withMessages([
                    'credential' => __('This account is inactive.'),
                ]);
            }

            return true;
        });
    }
}
