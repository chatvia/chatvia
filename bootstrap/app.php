<?php

use App\Http\Middleware\AuthenticateMember;
use App\Http\Middleware\AuthenticateMemberUsingToken;
use App\Http\Middleware\ChatHandleInertiaRequests;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureAgentHasMessagesCapacity;
use App\Http\Middleware\EnsureChatIsAccessible;
use App\Http\Middleware\EnsureUserCanAccessAgent;
use App\Http\Middleware\EnsureUserHasSubscription;
use App\Http\Middleware\ForceWww;
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
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration as SentryIntegration;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        dirname(__DIR__).'/src/App/Console/Commands',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (Application $app): void {
            Route::middleware('throttle:widgets')->group(__DIR__.'/../routes/widget.php');
        }
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('telescope:prune --hours=24')->dailyAt('00:00');
        $schedule->command('app:cache-message-usage')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            RedirectToAgent::class,
            HandleAppearance::class,
            //            ContentSecurityPolicy::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ForceWww::class,
        ]);

        $middleware->group('chat', [
            ResolveAgentFromSubdomain::class,
            RedirectToAgent::class,
            AuthenticateMemberUsingToken::class,
            EnsureChatIsAccessible::class,
            EnsureAgentHasMessagesCapacity::class,
            //            ContentSecurityPolicy::class,
            ChatHandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'auth.member' => AuthenticateMember::class,
            'guest.member' => MemberGuestMiddleware::class,
            'can.access.agent' => EnsureUserCanAccessAgent::class,
            'user.subscribed' => EnsureUserHasSubscription::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/chat/*',
            '/embed/*',
            '/widget/*',
            '/webhooks/*',
            '/stripe/*',
            '/ingestion/*',
        ]);

        // Set TRUSTED_PROXIES to a comma-separated list of upstream IPs/CIDRs (e.g. Cloudflare).
        // Wildcard fallback is for local/dev only; see AUDIT.md SEC-03.
        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $trustedProxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trustedProxies))))
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        if (app()->isProduction()) {
            SentryIntegration::handles($exceptions);
        }
    })->create();

// DDD layout: the Laravel framework surface lives at src/App/ rather than
// the default app/. This reroutes `app_path()` + framework discovery.
$app->useAppPath($app->basePath('src/App'));

return $app;
