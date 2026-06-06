<?php

use App\Http\Middleware\Access\EnsureAgentHasMessagesCapacity;
use App\Http\Middleware\Access\EnsureAgentPermission;
use App\Http\Middleware\Access\EnsureCanAccessApiAgent;
use App\Http\Middleware\Access\EnsureCanAccessDashboard;
use App\Http\Middleware\Access\EnsureTeamPermission;
use App\Http\Middleware\Access\EnsureUserCanAccessAgent;
use App\Http\Middleware\Access\EnsureUserHasCapability;
use App\Http\Middleware\Access\EnsureUserHasSubscription;
use App\Http\Middleware\Auth\AuthenticateChatterUsingToken;
use App\Http\Middleware\Auth\AuthenticateChatViaSignInToken;
use App\Http\Middleware\Auth\AuthenticateMember;
use App\Http\Middleware\Auth\MemberGuestMiddleware;
use App\Http\Middleware\Chat\ChatHandleInertiaRequests;
use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\RedirectToAgent;
use App\Http\Middleware\Chat\ResolveAgentFromSubdomain;
use App\Http\Middleware\Web\ContentSecurityPolicy;
use App\Http\Middleware\Web\ForceWww;
use App\Http\Middleware\Web\HandleAppearance;
use App\Http\Middleware\Web\HandleInertiaRequests;
use Support\Exceptions\DomainValidationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
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
        $schedule->command('app:prune-expired-data-exports')->dailyAt('00:15');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            RedirectToAgent::class,
            HandleAppearance::class,
            ContentSecurityPolicy::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ForceWww::class,
        ]);

        $middleware->group('chat', [
            ResolveAgentFromSubdomain::class,
            RedirectToAgent::class,
            AuthenticateChatterUsingToken::class,
            AuthenticateChatViaSignInToken::class,
            EnsureChatIsAccessible::class,
            EnsureAgentHasMessagesCapacity::class,
            ContentSecurityPolicy::class,
            ChatHandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'auth.member' => AuthenticateMember::class,
            'guest.member' => MemberGuestMiddleware::class,
            'can.access.agent' => EnsureUserCanAccessAgent::class,
            'user.subscribed' => EnsureUserHasSubscription::class,
            'api.agent' => EnsureCanAccessApiAgent::class,
            'can.access.dashboard' => EnsureCanAccessDashboard::class,
            'capability' => EnsureUserHasCapability::class,
            'agent.permission' => EnsureAgentPermission::class,
            'team.permission' => EnsureTeamPermission::class,
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
        // Trusting ALL proxies ('*') makes X-Forwarded-For attacker-spoofable, which
        // defeats every IP-keyed rate limiter and the widget IP fingerprint — so the
        // wildcard is only allowed in local/dev. In every other environment we trust
        // ONLY the explicitly-configured proxies (empty ⇒ trust none ⇒ use REMOTE_ADDR).
        // NB: app()->environment() is not resolvable this early in bootstrap,
        // so read APP_ENV via the env() helper directly.
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        $isLocal = env('APP_ENV') === 'local';

        $middleware->trustProxies(at: match (true) {
            $trustedProxies === '*' => $isLocal ? '*' : [],
            $trustedProxies === '' => $isLocal ? '*' : [],
            default => array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        if (app()->isProduction()) {
            SentryIntegration::handles($exceptions);
        }

        // Canonical HTTP translation for the domain's field-keyed rejection
        // exception: render it as a 422 validation error — JSON gets
        // {message, errors:{field:[...]}}, web/Inertia redirects back with the
        // field error. Controllers no longer hand-translate it; they only catch
        // it when they intentionally need a different shape (e.g. a 403 or a flash).
        $exceptions->map(fn (DomainValidationException $e) => ValidationException::withMessages([
            $e->field => [$e->getMessage()],
        ]));
    })->create();

// DDD layout: the Laravel framework surface lives at src/App/ rather than
// the default app/. This reroutes `app_path()` + framework discovery.
$app->useAppPath($app->basePath('src/App'));

return $app;
