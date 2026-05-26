<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('clients', [App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [App\Http\Controllers\ClientController::class, 'create'])->name('clients.create');
    Route::post('clients', [App\Http\Controllers\ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [App\Http\Controllers\ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{client}/edit', [App\Http\Controllers\ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{client}', [App\Http\Controllers\ClientController::class, 'update'])->name('clients.update');

    Route::get('users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}/sessions', [App\Http\Controllers\UserController::class, 'destroySessions'])->name('users.sessions.destroy');
    Route::patch('users/{user}/unlock', [App\Http\Controllers\UserController::class, 'unlock'])->name('users.unlock');
    Route::patch('users/{user}/toggle-active', [App\Http\Controllers\UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('users/{user}/clients', [App\Http\Controllers\UserController::class, 'syncClients'])->name('users.clients.sync');
});
