<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Chatter\Services\ChatterAuthTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\Member\AcceptAgentInvitationRequest;
use Domain\Member\Exceptions\AgentMemberInvitationRejectedException;
use Domain\Member\Actions\AcceptAgentInvitationAction;
use Domain\Member\Models\AgentMemberInvitation;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept an agent-invitation link (password path). The route param is the
 * invitation `token`; we resolve the matching {@see AgentMemberInvitation} row,
 * activate the chatter, delete the invitation (single-use), and hand the
 * session off to the agent's own subdomain via an `auth_token`.
 *
 * The redirect uses {@see Inertia::location()} on purpose: the form submits
 * via Inertia XHR, and the chat subdomain is a different host, so a normal
 * `redirect()->away()` would be swallowed by the Inertia client. The 409 +
 * `X-Inertia-Location` response triggers a full-page navigation instead.
 */
class AcceptAgentInvitationController extends Controller
{
    public function __invoke(
        AcceptAgentInvitationRequest $request,
        string $token,
        AcceptAgentInvitationAction $accept,
        ChatterAuthTokenService $authTokens,
    ): Response {
        $invitation = AgentMemberInvitation::query()
            ->with('agent')
            ->where('token', $token)
            ->first();

        abort_if($invitation === null || $invitation->hasExpired(), 404);

        $agent = $invitation->agent;

        try {
            $chatter = $accept->executeWithPassword(
                agent: $agent,
                email: $invitation->email,
                name: (string) $request->validated('name'),
                password: (string) $request->validated('password'),
            );
        } catch (AgentMemberInvitationRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        $invitation->delete();

        $authToken = $authTokens->generateToken($agent, $chatter, 5);

        return Inertia::location($agent->chat_url.'?auth_token='.$authToken);
    }
}
