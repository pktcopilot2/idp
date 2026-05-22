<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('clients', [App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');

    Route::get('users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::delete('users/{user}/sessions', [App\Http\Controllers\UserController::class, 'destroySessions'])->name('users.sessions.destroy');
    Route::patch('users/{user}/unlock', [App\Http\Controllers\UserController::class, 'unlock'])->name('users.unlock');
    Route::patch('users/{user}/toggle-active', [App\Http\Controllers\UserController::class, 'toggleActive'])->name('users.toggle-active');
});
