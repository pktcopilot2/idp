<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    /** @var \App\Models\User $user */
    $user = $request->user();

    return array_merge($user->toArray(), [
        'roles'       => $user->getRoleNames()->values()->toArray(),
        'permissions' => $user->getAllPermissions()->pluck('name')->values()->toArray(),
    ]);
})->middleware('auth:api');
