<?php

namespace App\Http\Controllers;

use App\Models\Passport\Client;
use App\Models\User;
use App\Http\Requests\DxDataGridRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Helpers\DxDatagridHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(): Response
    {
        return Inertia::render('users/Index', [
            'usersDataUrl' => route('users.data'),
            'clients' => Client::where('revoked', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * DevExtreme DataGrid data source for users.
     */
    public function usersData(DxDataGridRequest $request): JsonResponse
    {
        $allowedFields = ['id', 'name', 'email', 'username', 'active', 'locked_at', 'created_at', 'email_mfa_enabled', 'whatsapp_mfa_enabled', 'is_need_password_reset', 'failed_login_attempts'];

        $query = User::query()
            ->withCount('sessions as active_sessions_count')
            ->with(['assignedClients:id']);

        $result = DxDatagridHelper::fromRequest($request, $query, $allowedFields);

        return response()->json($result);
    }

    /**
     * DevExtreme DataGrid data source for a user's active sessions.
     */
    public function sessionsData(DxDataGridRequest $request, User $user): JsonResponse
    {
        $allowedFields = ['id', 'ip_address', 'user_agent', 'last_activity'];

        $query = $user->sessions()->select($allowedFields);

        $result = DxDatagridHelper::fromRequest($request, $query, $allowedFields);

        // Enrich each session row with the OAuth client names accessed during that session.
        $sessionIds = array_column($result['data'], 'id');

        $accesses = DB::table('session_client_accesses')
            ->join('oauth_clients', 'session_client_accesses.client_id', '=', 'oauth_clients.id')
            ->whereIn('session_client_accesses.session_id', $sessionIds)
            ->where('oauth_clients.revoked', false)
            ->select('session_client_accesses.session_id', 'oauth_clients.name')
            ->get();

        $clientsBySession = [];
        foreach ($accesses as $access) {
            $clientsBySession[$access->session_id][] = $access->name;
        }

        foreach ($result['data'] as &$row) {
            $row['clients'] = $clientsBySession[$row['id']] ?? [];
        }

        return response()->json($result);
    }

    /**
     * Revoke all active sessions for a user, forcing them to log out.
     */
    public function destroySessions(User $user): RedirectResponse
    {
        $user->sessions()->delete();
        $user->tokens()->update(['revoked' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sessions for {$user->name} have been revoked."]);

        return back();
    }

    /**
     * Unlock a locked user account and reset failed login attempts.
     */
    public function unlock(User $user): RedirectResponse
    {
        $user->update([
            'locked_at' => null,
            'failed_login_attempts' => 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name}'s account has been unlocked."]);

        return back();
    }

    /**
     * Sync assigned OAuth clients for a user.
     */
    public function syncClients(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['uuid', 'exists:oauth_clients,id'],
        ]);

        $user->assignedClients()->sync($validated['client_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Client assignments updated for {$user->name}."]);

        return back();
    }

    /**
     * Toggle a user's active status.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $user->update(['active' => ! $user->active]);

        $status = $user->active ? 'activated' : 'deactivated';
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name}'s account has been {$status}."]);

        return back();
    }

    /**
     * Show the detail page for a user.
     */
    public function show(User $user): Response
    {
        $user->load([
            'sessions:id,user_id,ip_address,user_agent,last_activity',
            'tokens' => fn ($q) => $q
                ->with('client:id,name')
                ->whereNotNull('name')
                ->where('revoked', false)
                ->select(['id', 'user_id', 'name', 'client_id']),
            'assignedClients:id,name,login_uri',
        ]);

        return Inertia::render('users/Show', [
            'user' => array_merge(
                $user->only(['id', 'name', 'username', 'email', 'active', 'email_mfa_enabled', 'whatsapp_mfa_enabled', 'whatsapp_number', 'is_need_password_reset', 'locked_at', 'failed_login_attempts', 'created_at']),
                [
                    'sessions' => $user->sessions,
                    'tokens' => $user->tokens,
                    'assigned_clients' => $user->assignedClients,
                ],
            ),
        ]);
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('users/Edit', [
            'user' => $user->only(['id', 'name', 'username', 'email', 'active', 'email_mfa_enabled', 'whatsapp_mfa_enabled', 'whatsapp_number', 'is_need_password_reset']),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->active = $request->boolean('active');
        $user->email_mfa_enabled = $request->boolean('email_mfa_enabled');
        $user->whatsapp_mfa_enabled = $request->boolean('whatsapp_mfa_enabled');
        if ($request->has('whatsapp_number')) {
            $user->whatsapp_number = $request->filled('whatsapp_number') ? $request->string('whatsapp_number')->toString() : null;
        }
        $user->is_need_password_reset = $request->boolean('is_need_password_reset');

        if ($request->filled('password')) {
            $user->password = $request->password;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} has been updated."]);

        return redirect()->route('users.index');
    }

    /**
     * Impersonate the given user.
     */
    public function impersonate(User $user): RedirectResponse
    {
        $admin = Auth::user();

        abort_if($admin->getAuthIdentifier() === $user->getAuthIdentifier(), 403, 'You cannot impersonate yourself.');
        abort_if($user->locked_at || ! $user->active, 403, 'You cannot impersonate a locked or inactive user.');
        abort_if(session()->has('impersonator_id'), 403, 'You are already impersonating another user.');

        session()->put('impersonator_id', $admin->getAuthIdentifier());

        Auth::login($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "You are now acting as {$user->name}."]);

        return redirect()->route('dashboard');
    }

    /**
     * Stop impersonating and return to the original user.
     */
    public function stopImpersonating(): RedirectResponse
    {
        $impersonatorId = session()->pull('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($impersonatorId);

        if ($admin) {
            Auth::login($admin);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'You are now back to your own account.']);
        }

        return redirect()->route('users.index');
    }
}
