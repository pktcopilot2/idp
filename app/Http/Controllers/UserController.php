<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users with their active session counts.
     */
    public function index(): Response
    {
        $users = User::query()
            ->addSelect([
                'active_sessions_count' => DB::table('sessions')
                    ->selectRaw('count(*)')
                    ->whereColumn('user_id', 'users.id'),
            ])
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
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->update(['revoked' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sessions for {$user->name} have been revoked."]);

        return back();
    }
}
