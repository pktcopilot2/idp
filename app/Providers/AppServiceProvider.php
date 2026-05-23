<?php

namespace App\Providers;

use App\Models\Passport\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('keycloak', \SocialiteProviders\Keycloak\Provider::class);
            $event->extendSocialite('fusionauth', \SocialiteProviders\FusionAuth\Provider::class);
        });
    }

    /**
     * Configure Laravel Passport settings.
     */
    protected function configurePassport(): void
    {
        Passport::useClientModel(Client::class);
        // Passport::authorizationView(fn ($parameters) => Inertia::render('oauth/Authorize', [
        //     'request' => $parameters['request'],
        //     'authToken' => $parameters['authToken'],
        //     'client' => $parameters['client'],
        //     'user' => $parameters['user'],
        //     'scopes' => $parameters['scopes'],
        // ]));

        Passport::authorizationView(function (array $params) {
            // This callback is only reached when skipsAuthorization() returns false,
            // meaning the user is not assigned to this client — deny access.
            $redirectUri = $params['request']->query('redirect_uri')
                ?? collect((array) $params['client']->redirect_uris)->first();

            $state = $params['request']->query('state');

            $query = http_build_query(array_filter([
                'error' => 'access_denied',
                'error_description' => 'You are not authorized to access this application.',
                'state' => $state,
            ]));

            if ($redirectUri) {
                $separator = str_contains($redirectUri, '?') ? '&' : '?';

                return redirect($redirectUri.$separator.$query);
            }

            abort(403, 'You are not authorized to access this application.');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
