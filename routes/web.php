<?php

use App\Http\Controllers\OidcController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/test', function () {
    // ...
})->name('test');

Route::get('/.well-known/openid-configuration', [OidcController::class, 'discovery'])->name('oidc.discovery');

Route::prefix('oidc')->group(function () {
    Route::get('/jwks', [OidcController::class, 'jwks'])->name('oidc.jwks');
    Route::post('/token', [OidcController::class, 'token'])
        ->middleware('throttle')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->name('oidc.token');
    Route::match(['GET', 'POST'], '/userinfo', [OidcController::class, 'userinfo'])
        ->middleware('auth:api')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->name('oidc.userinfo');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $clients = auth()->user()->assignedClients()
            ->select(['oauth_clients.id', 'oauth_clients.name', 'oauth_clients.login_uri'])
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'url' => $client->login_uri,
            ]);

        return inertia('Dashboard', ['clients' => $clients]);
    })->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
require __DIR__.'/master-data.php';
