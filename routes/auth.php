<?php

use Illuminate\Support\Facades\Route;

Route::get('/login/keycloak', fn() => redirect()->route('sso.keycloak.redirect'))->name('login.keycloak');
Route::get('/auth/keycloak/redirect', [\App\Http\Controllers\SsoController::class, 'redirectToKeycloak'])->name('sso.keycloak.redirect');
Route::get('/auth/keycloak/callback', [\App\Http\Controllers\SsoController::class, 'handleKeycloakCallback'])->name('sso.keycloak.callback');

Route::get('/login/fusionauth', fn() => redirect()->route('sso.fusionauth.redirect'))->name('login.fusionauth');
Route::get('/auth/fusionauth/redirect', [\App\Http\Controllers\SsoController::class, 'redirectToFusionauth'])->name('sso.fusionauth.redirect');
Route::get('/auth/fusionauth/callback', [\App\Http\Controllers\SsoController::class, 'handleFusionauthCallback'])->name('sso.fusionauth.callback');

Route::get('/email-mfa-challenge', [\App\Http\Controllers\EmailMfaChallengeController::class, 'create'])->name('email-mfa.create');
Route::post('/email-mfa-challenge', [\App\Http\Controllers\EmailMfaChallengeController::class, 'store'])->name('email-mfa.store')->middleware('throttle:email-mfa');
Route::post('/email-mfa-challenge/resend', [\App\Http\Controllers\EmailMfaChallengeController::class, 'resend'])->name('email-mfa.resend')->middleware('throttle:email-mfa');

Route::post('/logout', [\App\Http\Controllers\SsoController::class, 'logout'])->name('logout');
Route::get('/logout', [\App\Http\Controllers\SsoController::class, 'logout'])->name('logout.get');
