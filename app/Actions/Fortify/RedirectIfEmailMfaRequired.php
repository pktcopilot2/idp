<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\EmailMfaCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Fortify;

class RedirectIfEmailMfaRequired
{
    public function handle(Request $request, $next)
    {
        $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

        if (! $user || ! $user->email) {
            return $next($request);
        }

        // Users with confirmed TOTP are handled by Fortify's existing pipeline stage
        if ($user->two_factor_secret && ! is_null($user->two_factor_confirmed_at)) {
            return $next($request);
        }

        if (! $user->email_mfa_enabled) {
            return $next($request);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("email_mfa:{$user->getKey()}", $code, now()->addMinutes(5));

        $request->session()->put([
            'email_mfa.id' => $user->getKey(),
            'email_mfa.remember' => $request->boolean('remember'),
        ]);

        $user->notify(new EmailMfaCode($code));

        return redirect()->route('email-mfa.create');
    }
}
