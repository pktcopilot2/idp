<?php

use App\Helpers\LdapHelper;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/test', function () {
    // ...
})->name('test');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $clients = auth()->user()->assignedClients()
            ->select(['oauth_clients.id', 'oauth_clients.name', 'oauth_clients.redirect_uris'])
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                // url change the last / to /redirect example /auth/passport/callback to /auth/passport/redirect
                'url' => collect((array) $client->redirect_uris)
                    ->map(fn ($uri) => rtrim(parse_url($uri, PHP_URL_SCHEME).'://'.parse_url($uri, PHP_URL_HOST), '/').'/redirect')
                    ->first(),
            ]);

        return inertia('Dashboard', ['clients' => $clients]);
    })->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
require __DIR__.'/master-data.php';
