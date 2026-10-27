<?php

namespace App\Http\Middleware\Widget;

use App\Http\Widget\WidgetAgent;
use App\Identity\Workspace\Data\Sessions\ClientOriginData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessions;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the widget's chat-data routes behind the agent's embed switches and
 * its sign-in rule. The same `is_public` flag governs the hosted chat (see
 * EnsureChatIsAccessible), so one switch protects both surfaces.
 *
 *  - Embeds off: when the owner turned off both the Chat Widget and the
 *    Command Bar (Deploy page), or the workspace is deactivated
 *    ({@see WorkspaceActivation}), every call gets a 404, as the hosted chat
 *    does when its own surface is off. The embed script already stays hidden
 *    then; this stops hand-made calls from still getting answers.
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
        private readonly WidgetAuthSessions $sessions,
        private readonly WorkspaceActivation $activation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agent = WidgetAgent::from($request);

        if (! $agent instanceof Agent) {
            return response()->json(['error' => 'Could not verify the agent'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->embedIsOn($agent) || ! $this->activation->isActive($agent->workspace_id)) {
            return response()->json([
                'error' => 'This chat is turned off.',
                'code' => 'widget_disabled',
            ], Response::HTTP_NOT_FOUND);
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
     * Whether the Chat Widget or the Command Bar is on. Both are the same
     * embed script: they send identical requests and their conversations are
     * both stored as `widget`, so the server cannot tell which one is calling.
     * A surface named by the client could be faked by a hand-made call, so
     * the API serves the agent while either switch is on, and each embed
     * hides itself when its own switch is off (`widget_enabled` /
     * `bar_enabled` in the widget settings).
     */
    private function embedIsOn(Agent $agent): bool
    {
        return $agent->surfaceEnabled(ConversationSource::Widget)
            || $agent->surfaceEnabled(ConversationSource::Bar);
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

        $resolved = $this->sessions->resolveWithMeta($token, $agent, new ClientOriginData($request->ip(), $request->userAgent()));

        if (! $resolved) {
            return false;
        }

        $request->attributes->set(self::USER_ATTRIBUTE, $resolved['user']);
        $request->attributes->set(self::PLAYGROUND_ATTRIBUTE, (bool) ($resolved['meta']['is_playground'] ?? false));

        return true;
    }
}
