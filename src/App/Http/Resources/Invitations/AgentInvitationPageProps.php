<?php

declare(strict_types=1);

namespace App\Http\Resources\Invitations;

use Domain\Membership\Agent\Models\AgentChatterInvitation;

/**
 * Props of the agent-invitation onboarding page: the agent it grants, the
 * invited e-mail, and the accept URLs (password and social).
 */
final readonly class AgentInvitationPageProps
{
    /**
     * @return array<string, mixed>
     */
    public function for(AgentChatterInvitation $invitation): array
    {
        $agent = $invitation->agent;

        return [
            'agent' => [
                'external_id' => $agent->external_id,
                'name' => $agent->name,
            ],
            'email' => $invitation->email,
            'accept_url' => route('agents.invitation.accept', ['invitation' => $invitation->token]),
            'social_urls' => [
                'google' => route('agents.invitation.social.start', ['invitation' => $invitation->token, 'provider' => 'google']),
                'slack' => route('agents.invitation.social.start', ['invitation' => $invitation->token, 'provider' => 'slack']),
            ],
        ];
    }
}
