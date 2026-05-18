<?php

namespace App\Providers;

use App\Models\Passport\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Configure Laravel Passport settings.
     */
    protected function configurePassport(): void
    {
        Passport::useClientModel(Client::class);
        Passport::authorizationView(fn (array $params) => Inertia::render('oauth/Authorize', [
            'client' => [
                'id' => $params['client']->getKey(),
                'name' => $params['client']->name,
            ],
            'user' => [
                'name' => $params['user']->name,
            ],
            'scopes' => collect($params['scopes'])->map->toArray()->values(),
            'authToken' => $params['authToken'],
            'request' => $params['request']->all(),
            'csrfToken' => csrf_token(),
        ]));
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
