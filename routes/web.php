<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/revoke-sessions', function () {
    $user = User::where('username', 'k236615')->first();
    $user?->tokens()->update(['revoked' => true]);

    dd('Sessions revoked for user: ' . $user?->username);

    return redirect()->route('home');
})->name('revoke-sessions');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
require __DIR__.'/master-data.php';
