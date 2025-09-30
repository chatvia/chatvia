<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Http\Controllers\Controller;
use App\Models\Agent;

class AgentKnowledgeTextDestroyController
{
    public function __invoke(Agent $agent, string $id)
    {
        $agent->texts()->where('id', $id)->delete();
    }
}
