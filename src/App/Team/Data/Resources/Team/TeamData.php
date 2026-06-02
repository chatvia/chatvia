<?php

namespace App\Team\Data\Resources\Team;

use App\Team\Models\Team;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public TeamOwnerData $owner,
    ) {}

    public static function fromModel(Team $team): self
    {
        return new self(
            id: $team->id,
            name: $team->name,
            owner: TeamOwnerData::fromModel($team->owner),
        );
    }
}
