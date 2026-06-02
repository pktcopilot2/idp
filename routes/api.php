<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    /** @var \App\Models\User $user */
    $user = $request->user();

    // Scope roles/permissions to the client that issued this access token.
    $clientId  = $request->user()->currentAccessToken()?->oauth_client_id;
    $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
    $previousTeamId = $registrar->getPermissionsTeamId();

    $registrar->setPermissionsTeamId($clientId);

    $rolesWithPermissions = $user->roles->map(fn ($role) => [
        'name'        => $role->name,
        'permissions' => $role->permissions->pluck('name')->values()->toArray(),
    ])->values()->toArray();

    $directPermissions = $user->getDirectPermissions()->pluck('name')->values()->toArray();

    $allPermissions = $user->getAllPermissions()->pluck('name')->values()->toArray();

    $registrar->setPermissionsTeamId($previousTeamId);

    return array_merge($user->toArray(), [
        'roles'              => $rolesWithPermissions,
        'direct_permissions' => $directPermissions,
        'permissions'        => $allPermissions,
    ]);
})->middleware('auth:api');
