<?php

use App\Http\Middleware\Access\EnsureAgentHasMessagesCapacity;
use App\Http\Middleware\Access\EnsureAgentPermission;
use App\Http\Middleware\Access\EnsureApiWorkspaceOwner;
use App\Http\Middleware\Access\EnsureCanAccessDashboard;
use App\Http\Middleware\Access\EnsureTeamPermission;
use App\Http\Middleware\Access\EnsureUserCanAccessAgent;
use App\Http\Middleware\Access\EnsureUserHasCapability;
use App\Http\Middleware\Access\EnsureUserHasSubscription;
use App\Http\Middleware\Access\EnsureWorkspaceNetworkAllowed;
use App\Http\Middleware\Auth\AuthenticateChatter;
use App\Http\Middleware\Auth\AuthenticateChatterUsingToken;
use App\Http\Middleware\Auth\AuthenticateChatViaSignInToken;
use App\Http\Middleware\Auth\LogoutDeactivatedUser;
use App\Http\Middleware\Auth\RedirectIfChatterAuthenticated;
use App\Http\Middleware\Auth\RequirePasswordSetup;
use App\Http\Middleware\Chat\ChatHandleInertiaRequests;
use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\RedirectToAgent;
use App\Http\Middleware\Chat\ResolveAgentFromSubdomain;
use App\Http\Middleware\Web\ContentSecurityPolicy;
use App\Http\Middleware\Web\HandleAppearance;
use App\Http\Middleware\Web\HandleInertiaRequests;
use App\Http\Middleware\Web\SetLocale;
use App\Identity\Chatter\Exceptions\ChatterQuotaExceededException;
use Domain\Conversation\Exceptions\HandoffUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\LaravelFlare\Facades\Flare;
use Support\Exceptions\DomainValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            // Messaging-channel webhooks — bare public routes (no web group, so
            // no session or CSRF). Authenticity is verified inside the controller:
            // Telegram by the per-bot secret in the URL + the
            // X-Telegram-Bot-Api-Secret-Token header, Slack and Messenger by the
            // per-app secret in the URL + an HMAC request signature, Teams by
            // the Bot Framework's signed Bearer JWT.
            Route::group([], __DIR__.'/../routes/telegram.php');
            Route::group([], __DIR__.'/../routes/slack.php');
            Route::group([], __DIR__.'/../routes/messenger.php');
            Route::group([], __DIR__.'/../routes/whatsapp.php');
            Route::group([], __DIR__.'/../routes/msteams.php');
            Route::group([], __DIR__.'/../routes/instagram.php');
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            RedirectToAgent::class,
            HandleAppearance::class,
            SetLocale::class,
            RequirePasswordSetup::class,
            EnsureWorkspaceNetworkAllowed::class,
            // Also covers the hosted chat: chat routes run the `web` group too.
            // CSP_MODE (config/chatvia.php) picks off / report-only / enforce.
            ContentSecurityPolicy::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // RedirectToAgent is not listed here: chat routes already run it as
        // part of the `web` group above.
        $middleware->group('chat', [
            ResolveAgentFromSubdomain::class,
            AuthenticateChatterUsingToken::class,
            AuthenticateChatViaSignInToken::class,
            LogoutDeactivatedUser::class,
            EnsureChatIsAccessible::class,
            EnsureAgentHasMessagesCapacity::class,
            ChatHandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'auth.chatter' => AuthenticateChatter::class,
            'guest.chatter' => RedirectIfChatterAuthenticated::class,
            'can.access.agent' => EnsureUserCanAccessAgent::class,
            'user.subscribed' => EnsureUserHasSubscription::class,
            'api.owner' => EnsureApiWorkspaceOwner::class,
            'can.access.dashboard' => EnsureCanAccessDashboard::class,
            'capability' => EnsureUserHasCapability::class,
            'features' => EnsureFeaturesAreActive::class,
            'agent.permission' => EnsureAgentPermission::class,
            'team.permission' => EnsureTeamPermission::class,
            // Public-site localization (mcamara/laravel-localization): browser-detect
            // + redirect to the matching `/ar` URL, persisting the choice in session.
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
        ]);

        // Exempt only endpoints that the session cookie doesn't authenticate:
        // the embeddable widget (token auth) and server-to-server webhooks
        // (Stripe). The hosted chat (/chat/*) is cookie-authenticated,
        // so it keeps CSRF protection: its frontend sends the XSRF-TOKEN cookie
        // back as the X-XSRF-TOKEN header.
        $middleware->preventRequestForgery(except: [
            '/widget/*',
            '/stripe/*',
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
            $trustedProxies === '*', $trustedProxies === '' => $isLocal ? '*' : [],
            default => array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Flare::handles($exceptions);

        // Canonical HTTP translation for the domain's field-keyed rejection
        // exception: render it as a 422 validation error — JSON gets
        // {message, errors:{field:[...]}}, web/Inertia redirects back with the
        // field error. Controllers no longer hand-translate it; they only catch
        // it when they intentionally need a different shape (e.g. a 403 or a flash).
        $exceptions->map(fn (DomainValidationException $e) => ValidationException::withMessages([
            $e->field => [$e->getMessage()],
        ]));

        // A visitor asked for a human operator on an agent whose plan or
        // live-chat toggle does not allow it.
        $exceptions->map(fn (HandoffUnavailableException $e): AccessDeniedHttpException => new AccessDeniedHttpException($e->getMessage(), $e));

        // A workspace with no free seat asked for another chatter: 402 so API
        // clients can show an upgrade prompt.
        $exceptions->map(fn (ChatterQuotaExceededException $e): HttpException => new HttpException(402, $e->getMessage(), $e));
    })->create();

// DDD layout: the Laravel framework surface lives at src/App/ rather than
// the default app/. This reroutes `app_path()` + framework discovery.
$app->useAppPath($app->basePath('src/App'));

return $app;
