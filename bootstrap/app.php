<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\CaptureOidcNonce;
use App\Http\Middleware\HandleInvalidOauthClient;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrackClientAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;
use League\OAuth2\Server\Exception\OAuthServerException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            HandleInvalidOauthClient::class,
            TrackClientAccess::class,
            CaptureOidcNonce::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
