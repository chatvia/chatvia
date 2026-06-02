<?php

namespace Domain\Team\Data;

use App\Team\Models\Team;
use Spatie\LaravelData\Data;

class UpdateTeamData extends Data
{
    public function __construct(
        public Team $team,
        public string $name,
    ) {}
}
