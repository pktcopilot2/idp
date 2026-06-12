<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;

class SsoController extends Controller
{
    public function redirectToKeycloak()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function handleKeycloakCallback(Request $request)
    {
        $user = Socialite::driver('keycloak')->stateless()->user();
        $username = $user->user['preferred_username'];

        $localUser = \App\Models\User::query()->where('username', $username)->first();
        if (!$localUser) {
            return redirect()->route('login')->with(['message' => 'User not authorized.']);
        }

        if ($localUser->locked_at || !$localUser->active) {
            return redirect()->route('login')->with(['message' => 'Your account is locked or inactive. Please contact support.']);
        }

        session(['keycloak_token' => $user->token]);
        Auth::login($localUser, true);

        return redirect()->intended(route('dashboard'));
    }

    public function redirectToFusionauth()
    {
        return Socialite::driver('fusionauth')->redirect();
    }

    public function handleFusionauthCallback(Request $request)
    {
        $user = Socialite::driver('fusionauth')->stateless()->user();
        $username = optional($user->user)['preferred_username'];

        $localUser = \App\Models\User::query()->where('username', $username)->first();
        if (!$localUser) {
            return redirect()->route('login')->with(['message' => 'User not authorized.']);
        }

        if ($localUser->locked_at || !$localUser->active) {
            return redirect()->route('login')->with(['message' => 'Your account is locked or inactive. Please contact support.']);
        }

        session(['fusionauth_token' => $user->token]);
        Auth::login($localUser, true);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        if ($request->isMethod('get')) {
            return Inertia::render('auth/LogoutConfirmation', [
                'csrfToken' => csrf_token(),
            ]);
        }

        $user = $request->user();
        $user?->tokens()->update(['revoked' => true]);

        $fusionAuthToken = $request->session()->get('fusionauth_token');
        $keycloakToken = $request->session()->get('keycloak_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($fusionAuthToken) {
            return redirect(config('services.fusionauth.logout_url'));
        } elseif ($keycloakToken) {
            $keycloakLogoutUrl = Socialite::driver('keycloak')->getLogoutUrl(route('login'), config('services.keycloak.client_id'));
            return redirect($keycloakLogoutUrl);
        }

        return redirect()->route('login');
    }
}
