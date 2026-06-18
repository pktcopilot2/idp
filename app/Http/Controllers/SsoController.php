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
        return $this->socialiteDriver('keycloak')->redirect();
    }

    public function handleKeycloakCallback(Request $request)
    {
        $user = $this->socialiteDriverForCallback('keycloak')->user();
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
        return $this->socialiteDriver('fusionauth')->redirect();
    }

    public function handleFusionauthCallback(Request $request)
    {
        $user = $this->socialiteDriverForCallback('fusionauth')->user();
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
                'post_logout_redirect_uri' => $request->input('post_logout_redirect_uri', route('login')),
                'cancel_redirect_uri' => $request->input('cancel_redirect_uri', route('home')),
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
        } elseif ($request->input('post_logout_redirect_uri')) {
            return redirect($request->input('post_logout_redirect_uri'));
        }

        return redirect()->route('login');
    }

    protected function socialiteDriver(string $provider)
    {
        $driver = Socialite::driver($provider);

        if (config("services.{$provider}.pkce", false) && method_exists($driver, 'enablePKCE')) {
            $driver->enablePKCE();
        }

        return $driver;
    }

    protected function socialiteDriverForCallback(string $provider)
    {
        $driver = $this->socialiteDriver($provider);

        if (config("services.{$provider}.stateless", true) && method_exists($driver, 'stateless')) {
            $driver->stateless();
        }

        return $driver;
    }
}
