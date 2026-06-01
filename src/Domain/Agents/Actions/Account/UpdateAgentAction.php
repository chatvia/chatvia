<?php

namespace Domain\Agents\Actions\Account;

use Domain\Agents\Models\Agent;

class UpdateAgentAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Agent $agent, array $attributes): Agent
    {
        $agent->update($attributes);

        return $agent;
    }
}
