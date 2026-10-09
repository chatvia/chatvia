<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Data;

use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * Input for renaming a team.
 */
class UpdateTeamData extends Data
{
    public function __construct(
        public Team $team,
        public string $name,
    ) {}
}
