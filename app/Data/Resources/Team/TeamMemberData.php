<?php

namespace App\Data\Resources\Team;

use App\Models\Team;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamMemberData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public string $role_name,
        public bool $is_owner,
        public ?string $joined_at,
    ) {}

    public static function fromUser(User $user, Team $team): self
    {
        $role = $team->userRole($user);

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $role?->code ?? 'owner',
            role_name: $role?->name ?? 'Owner',
            is_owner: $team->user_id === $user->id,
            joined_at: $user->pivot?->created_at?->toDateTimeString(),
        );
    }
}
