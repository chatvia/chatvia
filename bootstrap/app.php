<?php

use App\Http\Middleware\EnsureChatIsAccessible;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectToAgent;
use App\Http\Middleware\ResolveAgentFromSubdomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            ResolveAgentFromSubdomain::class,
            RedirectToAgent::class,
            EnsureChatIsAccessible::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/chat/*',
            '/webhooks/*',
            '/stripe/*',
            '/ingestion/*'
        ]);

        $middleware->trustProxies([
            'chatvia.ai',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
