<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/security/email-mfa/initiate', [SecurityController::class, 'initiateEmailMfa'])->name('security.email-mfa.initiate')->middleware('throttle:5,10');
    Route::post('settings/security/email-mfa', [SecurityController::class, 'enableEmailMfa'])->name('security.email-mfa.enable');
    Route::delete('settings/security/email-mfa', [SecurityController::class, 'disableEmailMfa'])->name('security.email-mfa.disable');

    Route::post('settings/security/whatsapp-mfa', [SecurityController::class, 'enableWhatsappMfa'])->name('security.whatsapp-mfa.enable')->middleware('throttle:5,10');
    Route::post('settings/security/whatsapp-mfa/confirm', [SecurityController::class, 'confirmWhatsappMfa'])->name('security.whatsapp-mfa.confirm');
    Route::delete('settings/security/whatsapp-mfa', [SecurityController::class, 'disableWhatsappMfa'])->name('security.whatsapp-mfa.disable');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
