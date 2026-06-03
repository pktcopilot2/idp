<?php

namespace App\Http\Middleware;

use App\Models\Passport\Client;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HandleInvalidOauthClient
{
    /**
     * Intercept browser-based OAuth authorize requests and show a user-friendly page
     * when the client is missing or revoked.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('oauth/authorize') || $request->expectsJson()) {
            return $next($request);
        }

        $clientId = (string) $request->query('client_id', '');

        if ($clientId === '') {
            return Inertia::render('auth/OAuthClientInvalid', [
                'error' => 'invalid_client',
                'message' => 'Missing client_id in authorize request.',
                'client_id' => '',
            ])->toResponse($request)->setStatusCode(400);
        }

        $client = Client::query()
            ->select(['id', 'revoked'])
            ->find($clientId);

        if (! $client || (bool) $client->revoked) {
            return Inertia::render('auth/OAuthClientInvalid', [
                'error' => 'invalid_client',
                'message' => 'Client authentication failed. This application may have been revoked or is no longer valid.',
                'client_id' => $clientId,
            ])->toResponse($request)->setStatusCode(401);
        }

        return $next($request);
    }
}
