<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackClientAccess
{
    /**
     * Record which OAuth client a user accessed during their session.
     * Runs on GET /oauth/authorize while the user's browser session is available.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('get') && $request->is('oauth/authorize') && $request->user()) {
            $clientId = (string) $request->query('client_id', '');
            $sessionId = $request->session()->getId();

            if ($clientId !== '' && $sessionId !== '') {
                DB::table('session_client_accesses')->upsert(
                    [
                        'session_id' => $sessionId,
                        'client_id' => $clientId,
                        'last_accessed_at' => now(),
                    ],
                    ['session_id', 'client_id'],
                    ['last_accessed_at'],
                );
            }
        }

        return $next($request);
    }
}
