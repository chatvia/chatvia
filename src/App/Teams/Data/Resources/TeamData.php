<?php

namespace App\Teams\Data\Resources;

use App\Teams\Models\Team;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public TeamOwnerData $owner,
        public bool $can_access_dashboard,
    ) {}

    public static function fromModel(Team $team): self
    {
        return new self(
            id: $team->id,
            name: $team->name,
            owner: TeamOwnerData::fromModel($team->owner),
            can_access_dashboard: $team->can_access_dashboard,
        );
    }
}
