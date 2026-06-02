<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [App\Http\Controllers\Api\UserController::class, 'show']);
    Route::post('/clients/{client}/users/{user}', [App\Http\Controllers\Api\ClientUserController::class, 'assign']);
    Route::delete('/clients/{client}/users/{user}', [App\Http\Controllers\Api\ClientUserController::class, 'detach']);
});
