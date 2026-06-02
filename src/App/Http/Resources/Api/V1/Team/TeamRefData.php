<?php

namespace App\Http\Resources\Api\V1\Team;

use App\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * A lightweight team reference (id + name), embedded on an agent so you know
 * which team it belongs to without pulling the whole team tree.
 */
class TeamRefData extends Data
{
    public function __construct(
        /**
         * The team's ID.
         */
        public string $id,
        /**
         * The team's name.
         */
        public string $name,
    ) {}

    public static function fromModel(Team $team): self
    {
        return new self(
            id: $team->external_id,
            name: $team->name,
        );
    }
}
