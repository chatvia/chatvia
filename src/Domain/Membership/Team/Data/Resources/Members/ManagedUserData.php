<?php

namespace Domain\Membership\Team\Data\Resources\Members;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A user the workspace owner manages who can be added to a team straight away,
 * without an emailed invitation.
 */
#[TypeScript]
class ManagedUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
