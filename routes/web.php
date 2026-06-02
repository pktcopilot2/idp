<?php

use App\Helpers\LdapHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/test', function () {
    // ...
})->name('test');

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
