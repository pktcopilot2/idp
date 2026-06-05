<?php

namespace App\Actions\Fortify;

use App\Helpers\LdapHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class RedirectIfPasswordResetRequired
{
    public function handle(Request $request, $next)
    {
        $username = LdapHelper::getUserAliases($request->input(Fortify::username()));
        $user = User::whereIn(Fortify::username(), $username)->first();

        if (! $user) {
            return $next($request);
        }

        if (! $user->is_need_password_reset) {
            return $next($request);
        }

        $request->session()->put([
            'password_reset.id' => $user->getKey(),
            'password_reset.remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('force-password-reset.create');
    }
}
