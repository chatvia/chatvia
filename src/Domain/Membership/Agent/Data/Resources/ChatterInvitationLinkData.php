<?php

namespace Domain\Membership\Agent\Data\Resources;

use Domain\Membership\Agent\Data\AgentChatterInvitationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The sign-up link of a freshly sent chatter invitation, flashed back to the
 * Members pages so the owner can show and copy it.
 */
#[TypeScript]
class ChatterInvitationLinkData extends Data
{
    public function __construct(
        public string $email,
        public string $accept_url,
        public string $expires_at,
    ) {}

    public static function fromInvitation(AgentChatterInvitationData $invitation): self
    {
        return new self(
            email: $invitation->email,
            accept_url: route('agents.invitation.show', ['invitation' => $invitation->token]),
            expires_at: $invitation->expires_at->toIso8601String(),
        );
    }
}
