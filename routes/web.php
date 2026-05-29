<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/test', function () {
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $response = \Illuminate\Support\Facades\Http::withHeader('X-API-Key', config('services.whatsapp_mfa.api_key'))
        ->acceptJson()
        ->timeout(10)
        ->post(config('services.whatsapp_mfa.endpoint'), [
            'platform_id' => config('services.whatsapp_mfa.platform_id'),
            'external_id' => config('services.whatsapp_mfa.external_id'),
            'template_id' => config('services.whatsapp_mfa.template_id'),
            'header_media_url' => null,
            'callback_url' => null,
            'metadata' => [],
            'recipient' => [
                'type' => 'npk',
                'value' => 'k236615',
            ],
            'body_params' => [
                [
                    'model' => 1,
                    'value' => 'Eky',
                    'param_type' => 'body',
                ],
                [
                    'model' => 2,
                    'value' => $code,
                    'param_type' => 'body',
                ],
            ],
        ]);

    return $response->json();
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
