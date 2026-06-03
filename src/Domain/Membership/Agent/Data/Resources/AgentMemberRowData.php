<?php

namespace Domain\Membership\Agent\Data\Resources;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A workspace chatter as rendered on an agent's Members tab. We list every
 * chatter the workspace owns so the owner can grant access to this agent in
 * one click; `has_access` reflects whether the `agent_user` pivot row for
 * this specific agent exists. The pivot has no timestamps (project-wide
 * convention), so there's no per-grant `granted_at` to surface.
 */
#[TypeScript]
class AgentMemberRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public ?string $email,
        public string $status,
        public bool $has_access,
    ) {}

    public static function fromModelAndGrant(User $chatter, bool $hasAccess): self
    {
        return new self(
            external_id: $chatter->external_id,
            name: $chatter->name,
            email: $chatter->email,
            status: $chatter->email_verified_at !== null ? 'active' : 'pending',
            has_access: $hasAccess,
        );
    }
}
