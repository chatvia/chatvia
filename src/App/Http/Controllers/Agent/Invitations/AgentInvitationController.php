<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Http\Controllers\Controller;
use Domain\Membership\Agent\Models\AgentMemberInvitation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Land an agent-invitation link. The URL carries an opaque token; we look up
 * the matching {@see AgentMemberInvitation} row, check expiry, and render the
 * onboarding page with the carried (agent, email). Expired / unknown tokens
 * 404 — the page handles the "no longer valid" copy.
 */
class AgentInvitationController extends Controller
{
    public function __invoke(string $token): Response
    {
        $invitation = AgentMemberInvitation::query()
            ->with('agent')
            ->where('token', $token)
            ->first();

        abort_if($invitation === null || $invitation->hasExpired(), 404);

        $agent = $invitation->agent;

        return Inertia::render('auth/AcceptAgentInvitation', [
            'agent' => [
                'external_id' => $agent->external_id,
                'name' => $agent->name,
            ],
            'email' => $invitation->email,
            'accept_url' => route('agents.invitation.accept', ['token' => $token]),
            'social_urls' => [
                'google' => route('agents.invitation.social.start', ['token' => $token, 'provider' => 'google']),
                'slack' => route('agents.invitation.social.start', ['token' => $token, 'provider' => 'slack']),
            ],
        ]);
    }
}
