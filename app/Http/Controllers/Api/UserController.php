<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function show(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $clientId = $user->currentAccessToken()?->oauth_client_id;
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($clientId);

        try {
            $rolesWithPermissions = $user->roles->map(fn ($role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->toArray(),
            ])->values()->toArray();

            $directPermissions = $user->getDirectPermissions()->pluck('name')->values()->toArray();
            $allPermissions = $user->getAllPermissions()->pluck('name')->values()->toArray();

            return array_merge($user->toArray(), [
                'roles' => $rolesWithPermissions,
                'direct_permissions' => $directPermissions,
                'permissions' => $allPermissions,
            ]);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
