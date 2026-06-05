<?php

namespace Domain\Membership\Team\Data\Resources\Team;

use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $name,
        public TeamOwnerData $owner,
    ) {}

    public static function fromModel(Team $team): self
    {
        return new self(
            id: $team->id,
            external_id: $team->external_id,
            name: $team->name,
            owner: TeamOwnerData::fromModel($team->owner),
        );
    }
}
