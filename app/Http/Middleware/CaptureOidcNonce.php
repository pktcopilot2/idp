<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CaptureOidcNonce
{
    /**
     * Capture OIDC nonce from authorize request and bind it to authorization code.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('get') && $request->is('oauth/authorize')) {
            $state = (string) $request->query('state', '');
            $nonce = (string) $request->query('nonce', '');

            if ($state !== '' && $nonce !== '') {
                $nonceByState = (array) $request->session()->get('oidc_nonce_by_state', []);
                $nonceByState[$state] = $nonce;
                $request->session()->put('oidc_nonce_by_state', $nonceByState);

                Cache::put('oidc_nonce_state:'.$state, $nonce, now()->addMinutes(10));
            }
        }

        /** @var Response $response */
        $response = $next($request);

        if ($request->is('oauth/authorize') && $response->isRedirection()) {
            $location = (string) $response->headers->get('Location', '');

            if ($location !== '') {
                $queryString = parse_url($location, PHP_URL_QUERY) ?: '';
                parse_str($queryString, $queryParams);

                $code = isset($queryParams['code']) ? (string) $queryParams['code'] : '';
                $state = isset($queryParams['state']) ? (string) $queryParams['state'] : '';
                $requestNonce = (string) $request->query('nonce', '');

                if ($code !== '') {
                    $nonceByState = (array) $request->session()->get('oidc_nonce_by_state', []);
                    $nonce = null;

                    if ($state !== '') {
                        $nonce = $nonceByState[$state] ?? Cache::pull('oidc_nonce_state:'.$state);
                    }

                    if ((! is_string($nonce) || $nonce === '') && $requestNonce !== '') {
                        $nonce = $requestNonce;
                    }

                    if (is_string($nonce) && $nonce !== '') {
                        Cache::put('oidc_nonce:'.$code, $nonce, now()->addMinutes(10));
                    }

                    if ($state !== '') {
                        unset($nonceByState[$state]);
                        Cache::forget('oidc_nonce_state:'.$state);
                    }

                    $request->session()->put('oidc_nonce_by_state', $nonceByState);
                }
            }
        }

        return $response;
    }
}
