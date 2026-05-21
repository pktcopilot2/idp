<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordResetController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('password_reset.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/ForcePasswordReset', [
            'csrfToken' => csrf_token(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('password_reset.id');
        $remember = $request->session()->get('password_reset.remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $user->update([
            'password' => $request->password,
            'is_need_password_reset' => false,
        ]);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget(['password_reset.id', 'password_reset.remember']);

        return redirect()->intended(config('fortify.home'));
    }
}
