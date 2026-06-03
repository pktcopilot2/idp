<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('clients', [App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [App\Http\Controllers\ClientController::class, 'create'])->name('clients.create');
    Route::post('clients', [App\Http\Controllers\ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [App\Http\Controllers\ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{client}/edit', [App\Http\Controllers\ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{client}', [App\Http\Controllers\ClientController::class, 'update'])->name('clients.update');
    Route::patch('clients/{client}/revoke', [App\Http\Controllers\ClientController::class, 'revoke'])->name('clients.revoke');
    Route::patch('clients/{client}/restore', [App\Http\Controllers\ClientController::class, 'restore'])->name('clients.restore');
    Route::delete('clients/{client}', [App\Http\Controllers\ClientController::class, 'destroy'])->name('clients.destroy');

    // Client role & permission management
    Route::get('clients/{client}/roles', [App\Http\Controllers\ClientRoleController::class, 'index'])->name('clients.roles.index');
    Route::post('clients/{client}/roles', [App\Http\Controllers\ClientRoleController::class, 'storeRole'])->name('clients.roles.store');
    Route::put('clients/{client}/roles/{role}', [App\Http\Controllers\ClientRoleController::class, 'updateRole'])->name('clients.roles.update');
    Route::delete('clients/{client}/roles/{role}', [App\Http\Controllers\ClientRoleController::class, 'destroyRole'])->name('clients.roles.destroy');

    Route::post('clients/{client}/permissions', [App\Http\Controllers\ClientRoleController::class, 'storePermission'])->name('clients.permissions.store');
    Route::delete('clients/{client}/permissions/{permission}', [App\Http\Controllers\ClientRoleController::class, 'destroyPermission'])->name('clients.permissions.destroy');

    Route::get('clients/{client}/roles/assignments', [App\Http\Controllers\ClientRoleController::class, 'assignments'])->name('clients.roles.assignments');
    Route::put('clients/{client}/roles/assignments/{user}', [App\Http\Controllers\ClientRoleController::class, 'syncUserRoles'])->name('clients.roles.assignments.sync');

    Route::get('users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::get('users/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
    Route::delete('users/{user}/sessions', [App\Http\Controllers\UserController::class, 'destroySessions'])->name('users.sessions.destroy');
    Route::patch('users/{user}/unlock', [App\Http\Controllers\UserController::class, 'unlock'])->name('users.unlock');
    Route::patch('users/{user}/toggle-active', [App\Http\Controllers\UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('users/{user}/clients', [App\Http\Controllers\UserController::class, 'syncClients'])->name('users.clients.sync');

    Route::get('features', [App\Http\Controllers\FeatureFlagController::class, 'index'])->name('features.index');
    Route::patch('features/{feature}', [App\Http\Controllers\FeatureFlagController::class, 'update'])->name('features.update');
    Route::delete('features/{feature}/override', [App\Http\Controllers\FeatureFlagController::class, 'destroyOverride'])->name('features.override.destroy');
    Route::delete('features', [App\Http\Controllers\FeatureFlagController::class, 'purge'])->name('features.purge');
});
