<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users with their active sessions.
     */
    public function index(): Response
    {
        $users = User::query()
            ->with([
                'sessions:id,user_id,ip_address,user_agent,last_activity',
                'tokens' => fn ($q) => $q
                    ->with('client:id,name')
                    ->whereNotNull('name')
                    ->where('revoked', false)
                    ->select(['id', 'user_id', 'name', 'client_id']),
            ])
            ->withCount('sessions as active_sessions_count')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
        return Inertia::render('users/Index', [
            'users' => $users,
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
