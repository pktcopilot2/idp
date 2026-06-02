<?php

namespace App\Http\Controllers;

use App\Models\Passport\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClientRoleController extends Controller
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function withTeam(string $clientId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous  = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($clientId);
        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    // -----------------------------------------------------------------------
    // Roles
    // -----------------------------------------------------------------------

    /**
     * List all roles for the given client, with their permissions and user counts.
     */
    public function index(Client $client): Response
    {
        $roles = $this->withTeam($client->id, fn () =>
            Role::with('permissions:id,name')
                ->where('client_id', $client->id)
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'permissions' => $role->permissions->pluck('name')->values()->toArray(),
                    'users_count' => $role->users()->count(),
                ])
        );

        $allPermissions = Permission::where('client_id', $client->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();

        return Inertia::render('clients/roles/Index', [
            'client'         => $client->only(['id', 'name']),
            'roles'          => $roles,
            'all_permissions' => $allPermissions,
        ]);
    }

    /**
     * Store a new role for the given client.
     */
    public function storeRole(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $this->withTeam($client->id, function () use ($validated, $client) {
            // Ensure all listed permissions exist scoped to this client.
            foreach ($validated['permissions'] ?? [] as $perm) {
                Permission::firstOrCreate(
                    ['name' => $perm, 'client_id' => $client->id, 'guard_name' => 'web'],
                );
            }

            $role = Role::create([
                'name'      => $validated['name'],
                'client_id' => $client->id,
                'guard_name' => 'web',
            ]);

            if (! empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role \"{$validated['name']}\" created."]);

        return back();
    }

    /**
     * Update a role (rename + sync permissions).
     */
    public function updateRole(Request $request, Client $client, Role $role): RedirectResponse
    {
        abort_unless((string) $role->client_id === (string) $client->id, 403);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $this->withTeam($client->id, function () use ($validated, $client, $role) {
            foreach ($validated['permissions'] ?? [] as $perm) {
                Permission::firstOrCreate(
                    ['name' => $perm, 'client_id' => $client->id, 'guard_name' => 'web'],
                );
            }

            $role->name = $validated['name'];
            $role->save();
            $role->syncPermissions($validated['permissions'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role \"{$validated['name']}\" updated."]);

        return back();
    }

    /**
     * Delete a role.
     */
    public function destroyRole(Client $client, Role $role): RedirectResponse
    {
        abort_unless((string) $role->client_id === (string) $client->id, 403);

        $name = $role->name;
        $this->withTeam($client->id, fn () => $role->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role \"{$name}\" deleted."]);

        return back();
    }

    // -----------------------------------------------------------------------
    // Permissions (standalone)
    // -----------------------------------------------------------------------

    /**
     * Store a new standalone permission for the given client.
     */
    public function storePermission(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $this->withTeam($client->id, fn () =>
            Permission::firstOrCreate(
                ['name' => $validated['name'], 'client_id' => $client->id, 'guard_name' => 'web'],
            )
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permission \"{$validated['name']}\" created."]);

        return back();
    }

    /**
     * Delete a permission.
     */
    public function destroyPermission(Client $client, Permission $permission): RedirectResponse
    {
        abort_unless((string) $permission->client_id === (string) $client->id, 403);

        $name = $permission->name;
        $this->withTeam($client->id, fn () => $permission->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permission \"{$name}\" deleted."]);

        return back();
    }

    // -----------------------------------------------------------------------
    // User ↔ Role assignment
    // -----------------------------------------------------------------------

    /**
     * Show the page for assigning roles to users for this client.
     */
    public function assignments(Client $client): Response
    {
        $roles = $this->withTeam($client->id, fn () =>
            Role::where('client_id', $client->id)
                ->orderBy('name')
                ->get(['id', 'name'])
        );

        // Only show users that are assigned to this client.
        $users = $client->assignedUsers()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.username', 'users.email'])
            ->map(function (User $user) use ($client) {
                $userRoles = $this->withTeam($client->id, fn () =>
                    $user->roles()
                        ->pluck('roles.id')
                        ->values()
                        ->toArray()
                );

                return [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'username' => $user->username,
                    'email'    => $user->email,
                    'role_ids' => $userRoles,
                ];
            });

        return Inertia::render('clients/roles/Assignments', [
            'client' => $client->only(['id', 'name']),
            'roles'  => $roles,
            'users'  => $users,
        ]);
    }

    /**
     * Sync roles for a specific user within the given client.
     */
    public function syncUserRoles(Request $request, Client $client, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        // Verify every role belongs to this client.
        $roleIds = $validated['role_ids'] ?? [];
        if (! empty($roleIds)) {
            $valid = Role::whereIn('id', $roleIds)
                ->where('client_id', $client->id)
                ->pluck('id')
                ->all();
            abort_if(count($valid) !== count($roleIds), 422, 'One or more roles do not belong to this client.');
            $roleIds = $valid;
        }

        $this->withTeam($client->id, function () use ($user, $roleIds, $client) {
            // Detach all roles of this client from the user, then attach new ones.
            $clientRoleIds = Role::where('client_id', $client->id)->pluck('id')->all();
            $user->roles()->detach($clientRoleIds);

            if (! empty($roleIds)) {
                $roles = Role::whereIn('id', $roleIds)->get();
                $user->assignRole($roles);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Roles updated for {$user->name}."]);

        return back();
    }
}
