<?php

namespace Domain\Membership\Team\Data;

use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

class UpdateTeamData extends Data
{
    public function __construct(
        public Team $team,
        public string $name,
    ) {}
}
