<?php

namespace App\Actions\Fortify;

use App\Helpers\LdapHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

class Authenticate
{
    public function __invoke(Request $request): ?User
    {
        session([
            'url.intended' => session()->get('url.intended') ?? route('dashboard'),
        ]);

        $ldapAuth = LdapHelper::authAttempt($request->input(Fortify::username()), $request->password);
        if ($ldapAuth) {
            $aliases = LdapHelper::getUserAliases($request->input(Fortify::username()));
            $ldapUser = User::whereIn(Fortify::username(), $aliases)->first();
            if (! $ldapUser || $ldapUser?->isLocked() || ! $ldapUser?->active) {
                return null;
            }
            $ldapUser->update(['failed_login_attempts' => 0]);

            return $ldapUser;
        }

        $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

        if (! $user) {
            return null;
        }

        if ($user->isLocked() || ! $user->active) {
            return null;
        }

        if (Hash::check($request->password, $user->password)) {
            $user->update(['failed_login_attempts' => 0]);

            return $user;
        }

        $attempts = $user->failed_login_attempts + 1;
        $user->update([
            'failed_login_attempts' => $attempts,
            'locked_at' => $attempts >= 3 ? now() : null,
        ]);

        return null;
    }
}
