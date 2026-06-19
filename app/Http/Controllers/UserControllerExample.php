<?php

namespace App\Http\Controllers;

use App\Http\Requests\DxDataGridRequest;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function manage()
    {
        return inertia('users/Manage', [
            'usersDataUrl' => route('users.manage.data'),
            'canManageUsers' => auth()->user()->can('users.manage'),
            'canUpdateUsers' => auth()->user()->can('users.update'),
            'canDeleteUsers' => auth()->user()->can('users.delete'),
        ]);
    }

    public function manageData(DxDataGridRequest $request)
    {
        $allowedFields = ['id', 'name', 'email', 'email_verified_at', 'created_at'];
        $relations = [
            'roles' => [
                'select' => ['id', 'name'],
                'mode' => 'entity',
            ],
            'roles.permissions' => [
                'select' => ['id', 'name'],
                'mode' => 'entity',
            ],
        ];

        $query = User::query()
            ->select($allowedFields)
            ->latest();

        return response()->json($this->dxDataSource($request, $query, $allowedFields, $relations));
    }

    public function manageRolesData(DxDataGridRequest $request, User $user)
    {
        $allowedFields = ['id', 'name'];

        $query = Role::query()
            ->select($allowedFields)
            ->whereHas('users', fn ($roleUserQuery) => $roleUserQuery->whereKey($user->id));

        return response()->json($this->dxDataSource($request, $query, $allowedFields));
    }

    public function manageRolePermissionsData(DxDataGridRequest $request, User $user, Role $role)
    {
        $isRoleAssignedToUser = $user->roles()->whereKey($role->id)->exists();
        if (! $isRoleAssignedToUser) {
            abort(404);
        }

        $allowedFields = ['id', 'name'];

        $query = Permission::query()
            ->select($allowedFields)
            ->whereHas('roles', fn ($permissionRoleQuery) => $permissionRoleQuery->whereKey($role->id));

        return response()->json($this->dxDataSource($request, $query, $allowedFields));
    }
}
