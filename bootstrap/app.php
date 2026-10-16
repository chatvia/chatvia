<?php

use App\Http\Middleware\Access\EnsureAgentPermission;
use App\Http\Middleware\Access\EnsureApiWorkspaceOwner;
use App\Http\Middleware\Access\EnsureCanAccessDashboard;
use App\Http\Middleware\Access\EnsureTeamPermission;
use App\Http\Middleware\Access\EnsureUserCanAccessAgent;
use App\Http\Middleware\Access\EnsureWorkspaceNetworkAllowed;
use App\Http\Middleware\Access\EnsureWorkspaceOwner;
use App\Http\Middleware\Access\ScopeRequestToWorkspace;
use App\Http\Middleware\Auth\AuthenticateChatterUsingToken;
use App\Http\Middleware\Auth\AuthenticateChatViaSignInToken;
use App\Http\Middleware\Auth\LogoutDeactivatedUser;
use App\Http\Middleware\Chat\ChatHandleInertiaRequests;
use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\RedirectCustomDomainToChat;
use App\Http\Middleware\Chat\ResolveHostedChatAgent;
use App\Http\Middleware\Chat\UseHostedChatSession;
use App\Http\Middleware\Chat\UseSignedInChatGuard;
use App\Http\Middleware\Web\ContentSecurityPolicy;
use App\Http\Middleware\Web\HandleAppearance;
use App\Http\Middleware\Web\HandleInertiaRequests;
use App\Http\Middleware\Web\SetLocale;
use Domain\Conversation\Exceptions\ConversationNotHeldException;
use Domain\Conversation\Exceptions\HandoffUnavailableException;
use Domain\Conversation\Exceptions\VoiceCallRefusedException;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelFlare\Facades\Flare;
use Support\Exceptions\DomainValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../src/App/Console/Commands',
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

        // Before the `web` group starts the session: /chat requests get the
        // hosted chat's own session cookie and `chat` guard.
        $middleware->prepend(UseHostedChatSession::class);

        $middleware->web(append: [
            RedirectCustomDomainToChat::class,
            HandleAppearance::class,
            SetLocale::class,
            EnsureWorkspaceNetworkAllowed::class,
            // Also covers the hosted chat: chat routes run the `web` group too.
            // Settings → Security (`chatvia.security.csp`) picks off /
            // report-only / enforce.
            ContentSecurityPolicy::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The agent itself is resolved before this group, by `chat.agent` on
        // the /chat/{agentSlug} prefix (routes/chat.php).
        $middleware->group('chat', [
            UseSignedInChatGuard::class,
            AuthenticateChatterUsingToken::class,
            AuthenticateChatViaSignInToken::class,
            LogoutDeactivatedUser::class,
            EnsureChatIsAccessible::class,
            ChatHandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'chat.agent' => ResolveHostedChatAgent::class,
            'can.access.agent' => EnsureUserCanAccessAgent::class,
            'workspace.scope' => ScopeRequestToWorkspace::class,
            'api.owner' => EnsureApiWorkspaceOwner::class,
            'workspace.owner' => EnsureWorkspaceOwner::class,
            'can.access.dashboard' => EnsureCanAccessDashboard::class,
            'agent.permission' => EnsureAgentPermission::class,
            'team.permission' => EnsureTeamPermission::class,
        ]);

        // `guest` routes: a visitor already signed in on the chat's login page
        // goes back to that chat; anywhere else to the dashboard.
        $middleware->redirectUsersTo(fn (Request $request): string => $request->is('chat/*')
            ? route('chat.home')
            : route('dashboard'));

        // Exempt only endpoints that the session cookie doesn't authenticate:
        // the embeddable widget (token auth) and the widget package
        // (shakomako), which runs on other sites. The hosted chat (/chat/*) is
        // cookie-authenticated, so it keeps CSRF protection: its frontend sends
        // the page's token as the X-CSRF-TOKEN header (resources/js/utils/csrf.ts).
        $middleware->preventRequestForgery(except: [
            '/widget/*',
            '/shakomako/*',
        ]);

        // The trusted proxies (TRUSTED_PROXIES) are read from
        // config/trustedproxy.php on every request: this runs before .env is
        // loaded, so env() would see nothing here.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Flare::handles($exceptions);

        // The public API always answers errors as JSON, even when a client
        // leaves out `Accept: application/json`: a 401 or a 422, never a
        // redirect to the login page or back to a form.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());

        // Canonical HTTP translation for the domain's field-keyed rejection
        // exception: render it as a 422 validation error — JSON gets
        // {message, errors:{field:[...]}}, web/Inertia redirects back with the
        // field error. Controllers no longer hand-translate it; they only catch
        // it when they intentionally need a different shape (e.g. a 403 or a flash).
        $exceptions->map(fn (DomainValidationException $e) => ValidationException::withMessages([
            $e->field => [$e->getMessage()],
        ]));

        // A visitor asked for a human operator on an agent whose live-chat
        // toggle does not allow it.
        $exceptions->map(fn (HandoffUnavailableException $e): AccessDeniedHttpException => new AccessDeniedHttpException($e->getMessage(), $e));
        $exceptions->map(fn (ConversationNotHeldException $e): AccessDeniedHttpException => new AccessDeniedHttpException($e->getMessage(), $e));
        $exceptions->map(fn (TeamOwnerImmutableException $e): AccessDeniedHttpException => new AccessDeniedHttpException($e->getMessage(), $e));
        // A refused voice call: the chat clients switch on the code.
        $exceptions->render(fn (VoiceCallRefusedException $e): JsonResponse => response()->json([
            'error' => $e->refusal->value,
            'message' => $e->getMessage(),
        ], $e->refusal->status()));
    })->create();

// DDD layout: the Laravel framework surface lives at src/App/ rather than
// the default app/. This reroutes `app_path()` + framework discovery.
$app->useAppPath($app->basePath('src/App'));

return $app;
