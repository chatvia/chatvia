<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Data;

use App\Identity\Workspace\Models\Workspace;
use Spatie\LaravelData\Data;

/**
 * Input for creating a team: the workspace it belongs to and its name.
 */
class CreateTeamData extends Data
{
    public function __construct(
        public Workspace $workspace,
        public string $name,
    ) {}
}
