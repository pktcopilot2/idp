<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        session(['keycloak_token' => $user->token]);

        $localUser = \App\Models\User::query()->where('username', $username)->first();
        if (!$localUser) {
            return redirect()->route('login')->withErrors(['email' => 'User not found in local database.']);
        }

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
        session(['fusionauth_token' => $user->token]);

        $localUser = \App\Models\User::query()->where('username', $username)->first();
        if (!$localUser) {
            return redirect()->route('login')->withErrors(['email' => 'User not found in local database.']);
        }

        Auth::login($localUser, true);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
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
