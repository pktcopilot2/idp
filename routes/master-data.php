<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('clients', [App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
});
