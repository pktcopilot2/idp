<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('clients', [App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');

    Route::get('users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::delete('users/{user}/sessions', [App\Http\Controllers\UserController::class, 'destroySessions'])->name('users.sessions.destroy');
});
