<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Models\Agent;

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
