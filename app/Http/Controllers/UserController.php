<?php

namespace App\Http\Controllers;

use App\Models\Passport\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users with their active sessions.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', '20');

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        $allowedSorts = ['name', 'username', 'email', 'active_sessions_count'];
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'name';
        }
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query = User::query()
            ->with([
                'sessions:id,user_id,ip_address,user_agent,last_activity',
                'tokens' => fn ($q) => $q
                    ->with('client:id,name')
                    ->whereNotNull('name')
                    ->where('revoked', false)
                    ->select(['id', 'user_id', 'name', 'client_id']),
                'assignedClients:id',
            ])
            ->withCount('sessions as active_sessions_count')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
            ))
            ->orderBy($sort, $direction);

        if ($perPage === 'all') {
            $collection = $query->get();
            $count = $collection->count();
            $users = new LengthAwarePaginator(
                $collection, $count, $count ?: 1, 1,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            $users = $query->paginate(max(1, (int) $perPage))->withQueryString();
        }

        return Inertia::render('users/Index', [
            'users' => $users,
            'clients' => Client::where('revoked', false)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search ?? '',
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
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
}
