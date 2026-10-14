<?php

namespace App\Http\Middleware\Widget;

use App\Http\Widget\WidgetAgent;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the widget's chat-data routes behind the agent's sign-in rule. The
 * same `is_public` flag governs the hosted chat (see EnsureChatIsAccessible),
 * so one switch protects both surfaces.
 *
 *  - Public agent: always passes. When the call carries a valid X-AUTH-TOKEN
 *    anyway (a signed-in visitor, or the dashboard playground) the user is
 *    resolved on a best-effort basis, so actions can be attributed to them.
 *  - Protected agent: the call must carry a valid X-AUTH-TOKEN — a session
 *    token minted by the auth exchange for a user of this agent's account. An
 *    absent, expired or fingerprint-mismatched token gets a 401, so the widget
 *    knows to run the exchange again.
 *
 * A resolved token puts two request attributes in place for the controllers:
 * `widget_user` (the {@see User}) and `widget_is_playground` (from the
 * bootstrap token's `ctx` claim). Bootstrap routes (agents/verify, settings,
 * auth exchange) sit outside this middleware so a protected widget can still
 * render its locked shell and sign in.
 */
class EnsureWidgetIsAccessible
{
    public const string USER_ATTRIBUTE = 'widget_user';

    public const string PLAYGROUND_ATTRIBUTE = 'widget_is_playground';

    public function __construct(
        private readonly WidgetAuthSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agent = WidgetAgent::from($request);

        if (! $agent instanceof Agent) {
            return response()->json(['error' => 'Could not verify the agent'], Response::HTTP_FORBIDDEN);
        }

        // Jobs this request queues carry the agent's workspace.
        Context::addHidden('workspace_id', $agent->workspace_id);

        $resolved = $this->resolveSignedInUser($request, $agent);

        if (! $agent->is_public && ! $resolved) {
            return response()->json([
                'error' => 'This chat requires sign-in.',
                'code' => 'widget_auth_required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    /**
     * Resolve the X-AUTH-TOKEN header (when present) and attach its user to the
     * request. Returns whether a valid token was found.
     */
    private function resolveSignedInUser(Request $request, Agent $agent): bool
    {
        $token = $request->headers->get(WidgetHeaders::AUTH_TOKEN);

        if (blank($token)) {
            return false;
        }

        $resolved = $this->sessions->resolveWithMeta($token, $agent, $request);

        if (! $resolved) {
            return false;
        }

        $request->attributes->set(self::USER_ATTRIBUTE, $resolved['user']);
        $request->attributes->set(self::PLAYGROUND_ATTRIBUTE, (bool) ($resolved['meta']['is_playground'] ?? false));

        return true;
    }
}
