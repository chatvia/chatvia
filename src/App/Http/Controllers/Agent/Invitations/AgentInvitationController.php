<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Http\Resources\Invitations\AgentInvitationPageProps;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Land an agent-invitation link. The URL carries an opaque token the route
 * binds as `{invitation:token}`; we check expiry and render the onboarding
 * page with the carried (agent, email). Expired / unknown tokens 404 — the
 * page handles the "no longer valid" copy.
 */
class AgentInvitationController
{
    public function __invoke(AgentInvitationPageProps $props, AgentChatterInvitation $invitation): Response
    {
        $invitation->loadMissing('agent');

        // A deleted agent is hidden from the relation, so its invitations are gone too.
        abort_if($invitation->hasExpired() || $invitation->agent === null, 404);

        return Inertia::render('auth/AcceptAgentInvitation', $props->for($invitation));
    }
}
