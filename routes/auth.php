<?php

use Illuminate\Support\Facades\Route;

Route::get('/login/keycloak', fn() => redirect()->route('sso.keycloak.redirect'))->name('login.keycloak');
Route::get('/auth/keycloak/redirect', [\App\Http\Controllers\SsoController::class, 'redirectToKeycloak'])->name('sso.keycloak.redirect');
Route::get('/auth/keycloak/callback', [\App\Http\Controllers\SsoController::class, 'handleKeycloakCallback'])->name('sso.keycloak.callback');

Route::get('/login/fusionauth', fn() => redirect()->route('sso.fusionauth.redirect'))->name('login.fusionauth');
Route::get('/auth/fusionauth/redirect', [\App\Http\Controllers\SsoController::class, 'redirectToFusionauth'])->name('sso.fusionauth.redirect');
Route::get('/auth/fusionauth/callback', [\App\Http\Controllers\SsoController::class, 'handleFusionauthCallback'])->name('sso.fusionauth.callback');

Route::post('/login/passwordless', [\App\Http\Controllers\PasswordlessLoginController::class, 'store'])->name('passwordless.store')->middleware('throttle:login');
Route::get('/login/passwordless/method', [\App\Http\Controllers\PasswordlessLoginController::class, 'selectMethod'])->name('passwordless.method.select');
Route::post('/login/passwordless/method', [\App\Http\Controllers\PasswordlessLoginController::class, 'chooseMethod'])->name('passwordless.method.choose')->middleware('throttle:6,1');

Route::get('/email-mfa-challenge', [\App\Http\Controllers\EmailMfaChallengeController::class, 'create'])->name('email-mfa.create');
Route::post('/email-mfa-challenge', [\App\Http\Controllers\EmailMfaChallengeController::class, 'store'])->name('email-mfa.store')->middleware('throttle:email-mfa');
Route::post('/email-mfa-challenge/resend', [\App\Http\Controllers\EmailMfaChallengeController::class, 'resend'])->name('email-mfa.resend')->middleware('throttle:email-mfa');

Route::get('/whatsapp-mfa-challenge', [\App\Http\Controllers\WhatsappMfaChallengeController::class, 'create'])->name('whatsapp-mfa.create');
Route::post('/whatsapp-mfa-challenge', [\App\Http\Controllers\WhatsappMfaChallengeController::class, 'store'])->name('whatsapp-mfa.store')->middleware('throttle:whatsapp-mfa');
Route::post('/whatsapp-mfa-challenge/resend', [\App\Http\Controllers\WhatsappMfaChallengeController::class, 'resend'])->name('whatsapp-mfa.resend')->middleware('throttle:whatsapp-mfa');

Route::get('/force-password-reset', [\App\Http\Controllers\ForcePasswordResetController::class, 'create'])->name('force-password-reset.create');
Route::post('/force-password-reset', [\App\Http\Controllers\ForcePasswordResetController::class, 'store'])->name('force-password-reset.store');

Route::post('/logout', [\App\Http\Controllers\SsoController::class, 'logout'])->name('logout');
Route::get('/logout', [\App\Http\Controllers\SsoController::class, 'logout'])->name('logout.get');
