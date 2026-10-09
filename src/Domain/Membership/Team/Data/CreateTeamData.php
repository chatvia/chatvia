<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Input for creating a team: its owner and name.
 */
class CreateTeamData extends Data
{
    public function __construct(
        public User $owner,
        public string $name,
    ) {}
}
