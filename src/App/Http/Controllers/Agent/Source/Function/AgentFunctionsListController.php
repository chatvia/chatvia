<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Actions\ListAgentFunctionsAction;
use Domain\Integration\Function\Data\Resources\AgentFunctionsListResourceData;

/**
 * JSON for the agent's Functions page: every workspace function and whether
 * this agent uses it.
 */
class AgentFunctionsListController
{
    public function __invoke(Agent $agent, ListAgentFunctionsAction $list): AgentFunctionsListResourceData
    {
        return $list->execute($agent);
    }
}
