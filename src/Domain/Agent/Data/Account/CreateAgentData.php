<?php

namespace Domain\Agent\Data\Account;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * Inputs for `CreateAgentAction::execute()`. Owner (`user`) and `team` arrive
 * from the form request — the signed-in user and the team they are working in
 * (`User::currentOrPrimaryTeam()`) — so the action gets a single typed bundle
 * instead of three positional arguments.
 */
class CreateAgentData extends Data
{
    public function __construct(
        public User $user,
        public Team $team,
        public string $name,
    ) {}
}
