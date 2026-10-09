<?php

namespace Domain\Membership\Team\Data\Resources\Members;

use Domain\Membership\Team\Models\Invitation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A pending team invitation as listed on the team page.
 */
#[TypeScript]
class TeamInvitationData extends Data
{
    public function __construct(
        public int $id,
        public string $email,
        public ?string $role,
        public ?string $role_name,
        public string $created_at,
    ) {}

    public static function fromModel(Invitation $invitation): self
    {
        return new self(
            id: $invitation->id,
            email: $invitation->email,
            role: $invitation->role->code,
            role_name: $invitation->role->name,
            created_at: $invitation->created_at->toDateTimeString(),
        );
    }
}
