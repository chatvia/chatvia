<?php

namespace Domain\Teams\Data;

use App\Teams\Models\Team;
use Spatie\LaravelData\Data;

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
