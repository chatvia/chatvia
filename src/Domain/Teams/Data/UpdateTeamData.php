<?php

namespace Domain\Teams\Data;

use App\Teams\Models\Team;
use Spatie\LaravelData\Data;

class UpdateTeamData extends Data
{
    public function __construct(
        public Team $team,
        public string $name,
        public bool $canAccessDashboard = true,
    ) {}
}
