<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Http\Requests\Agent\Chatter\AcceptAgentInvitationRequest;
use App\Identity\Chatter\Services\ChatterAuthTokenService;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept an agent-invitation link (password path). The route param is the
 * invitation `token`; we resolve the matching {@see AgentChatterInvitation} row
 * and hand it to the accept Action, which checks expiry, activates the chatter
 * and deletes the invitation (single use) in one transaction. The session is
 * then handed off to the agent's own subdomain via an `auth_token`.
 *
 * The redirect uses {@see Inertia::location()} on purpose: the form submits
 * via Inertia XHR, and the chat subdomain is a different host, so a normal
 * `redirect()->away()` would be swallowed by the Inertia client. The 409 +
 * `X-Inertia-Location` response triggers a full-page navigation instead.
 */
class AcceptAgentInvitationController
{
    public function __invoke(
        AcceptAgentInvitationRequest $request,
        string $token,
        AcceptAgentInvitationAction $accept,
        ChatterAuthTokenService $authTokens,
    ): Response {
        $invitation = AgentChatterInvitation::query()
            ->with('agent')
            ->where('token', $token)
            ->first();

        abort_if($invitation === null || $invitation->agent === null, 404);

        $agent = $invitation->agent;

        try {
            $chatter = $accept->execute($request->toData($invitation));
        } catch (AgentChatterInvitationRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        $authToken = $authTokens->generateToken($agent, $chatter, 5);

        return Inertia::location($agent->chat_url.'?auth_token='.$authToken);
    }
}
