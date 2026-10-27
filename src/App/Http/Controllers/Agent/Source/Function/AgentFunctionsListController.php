<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\Resources\AgentFunctionsListResourceData;
use Domain\Integration\Function\Queries\AgentFunctionsQuery;

/**
 * JSON for the agent's Functions page: every workspace function and whether
 * this agent uses it.
 */
class AgentFunctionsListController
{
    public function __invoke(Agent $agent, AgentFunctionsQuery $list): AgentFunctionsListResourceData
    {
        return $list->get($agent);
    }
}
