<?php

namespace Domain\Membership\Agent\Data\Resources;

use App\Identity\Chatter\Models\Chatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A workspace chatter as rendered on an agent's Members tab. We list every
 * chatter the workspace owns so the owner can grant access to this agent in
 * one click; `has_access` reflects whether the `agent_chatter` pivot row for
 * this specific agent exists. The pivot has no timestamps (project-wide
 * convention), so there's no per-grant `granted_at` to surface.
 */
#[TypeScript]
class AgentChatterRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public string $status,
        public bool $has_access,
        public string $created_at,
        // Only set for a pending member that still has a live (non-expired)
        // invitation, so the owner can copy/share the sign-up link from the UI.
        public ?string $invitation_url = null,
        public ?string $invitation_expires_at = null,
    ) {}

    public static function fromModelAndGrant(
        Chatter $chatter,
        bool $hasAccess,
        ?string $invitationUrl = null,
        ?string $invitationExpiresAt = null,
    ): self {
        return new self(
            external_id: $chatter->external_id,
            name: $chatter->name,
            email: $chatter->email,
            avatar: $chatter->avatar,
            status: $chatter->email_verified_at !== null ? 'active' : 'pending',
            has_access: $hasAccess,
            created_at: $chatter->created_at->toIso8601String(),
            invitation_url: $invitationUrl,
            invitation_expires_at: $invitationExpiresAt,
        );
    }
}
