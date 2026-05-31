<?php

namespace Domain\Agents\Data;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Spatie\LaravelData\Data;

/**
 * Inputs for `CreateAgentAction::execute()`. Owner (`user`) and `team` arrive
 * from the controller — both rely on `current_team()` + auth context — so the
 * action gets a single typed bundle instead of three positional arguments.
 */
class CreateAgentData extends Data
{
    public function __construct(
        public User $user,
        public Team $team,
        public string $name,
    ) {}
}
