<?php

use App\Http\Middleware\AuthenticateMember;
use App\Http\Middleware\AuthenticateMemberUsingToken;
use App\Http\Middleware\ChatHandleInertiaRequests;
use App\Http\Middleware\EnsureAgentHasMessagesCapacity;
use App\Http\Middleware\EnsureChatIsAccessible;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MemberGuestMiddleware;
use App\Http\Middleware\RedirectToAgent;
use App\Http\Middleware\ResolveAgentFromSubdomain;
use Illuminate\Console\Scheduling\Schedule;
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
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('telescope:prune --hours=48')->daily();
        $schedule->command('app:update-free-users-quotas')->dailyAt('00:00');
        $schedule->command('app:cache-message-usage')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            ResolveAgentFromSubdomain::class,
            EnsureChatIsAccessible::class,
            RedirectToAgent::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->group('chat', [
            ResolveAgentFromSubdomain::class,
            RedirectToAgent::class,
            EnsureChatIsAccessible::class,
            EnsureAgentHasMessagesCapacity::class,
            AuthenticateMemberUsingToken::class,
            ChatHandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'auth.member' => AuthenticateMember::class,
            'guest.member' => MemberGuestMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/chat/*',
            '/webhooks/*',
            '/stripe/*',
            '/ingestion/*',
            '/agents/*/playground/*',
        ]);

        $middleware->trustProxies('*');
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();
