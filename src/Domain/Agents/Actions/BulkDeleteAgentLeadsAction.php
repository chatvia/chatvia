<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class BulkDeleteAgentLeadsAction
{
    /**
     * @param  array<int, int>  $leadIds
     */
    public function execute(Agent $agent, array $leadIds): int
    {
        return $agent->leads()->whereIn('id', $leadIds)->delete();
    }
}
