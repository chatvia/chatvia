<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Data;

use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * Input for creating or updating a team role: its name, permission codes, and
 * agent access (every agent, or the listed agent external ids).
 */
class SaveTeamRoleData extends Data
{
    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $agentExternalIds
     */
    public function __construct(
        public Team $team,
        public string $name,
        public array $permissions,
        public bool $allAgents,
        public array $agentExternalIds,
    ) {}
}
