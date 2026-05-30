<?php

namespace App\Http\Middleware;

use App\Auth\Services\WidgetAuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates protected widgets. Public agents (is_public = true) pass untouched — the
 * same `is_public` flag governs the hosted chat (see EnsureChatIsAccessible), so
 * one toggle protects both surfaces.
 *
 * For a protected agent the request must carry a valid `X-AUTH-TOKEN`: a session
 * token minted by the auth-exchange endpoint for an external user of this
 * agent's account. The resolved user (and any session meta — e.g. the
 * `is_playground` flag carried from the bootstrap JWT's `ctx` claim) is
 * attached to the request; an absent, expired, or fingerprint-mismatched token
 * is rejected with 401 so the widget can prompt to re-exchange.
 *
 * Bootstrap routes (auth exchange, settings, verify, version) sit OUTSIDE this
 * middleware so the widget can render its locked shell and run the exchange.
 */
class EnsureWidgetIsAccessible
{
    public function __construct(
        private readonly WidgetAuthSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agent = current_agent_from_widget();

        if (! $agent) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        if ($agent->is_public) {
            return $next($request);
        }

        $token = $request->header('X-AUTH-TOKEN');

        $resolved = is_string($token) && $token !== ''
            ? $this->sessions->resolveWithMeta($token, $agent, $request)
            : null;

        if (! $resolved) {
            return response()->json([
                'error' => 'This chat requires sign-in.',
                'code' => 'widget_auth_required',
            ], 401);
        }

        $request->attributes->set('widget_user', $resolved['user']);
        $request->attributes->set('widget_is_playground', (bool) ($resolved['meta']['is_playground'] ?? false));

        return $next($request);
    }
}
