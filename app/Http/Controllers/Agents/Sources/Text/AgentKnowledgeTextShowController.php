<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;

class AgentKnowledgeTextShowController
{
    public function __invoke(Agent $agent, int $id)
    {
        return $agent->texts()->find($id);
    }
}
