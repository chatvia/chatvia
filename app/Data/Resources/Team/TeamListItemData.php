<?php

namespace App\Data\Resources\Team;

use App\Models\Team;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public TeamOwnerData $owner,
        public bool $is_owner,
        public bool $is_primary,
        public string $role,
        public int $members_count,
        public int $agents_count,
        public string $created_at,
    ) {}

    public static function fromTeamForUser(Team $team, User $user): self
    {
        return new self(
            id: $team->id,
            name: $team->name,
            owner: TeamOwnerData::fromModel($team->owner),
            is_owner: $user->ownsTeam($team),
            is_primary: $user->isPrimaryTeam($team),
            role: $user->teamRole($team)?->code ?? 'owner',
            members_count: $team->users()->count(), // includes owner
            agents_count: $team->agents()->count(),
            created_at: $team->created_at->toDateTimeString(),
        );
    }
}
