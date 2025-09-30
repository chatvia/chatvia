<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;

class AgentKnowledgeTextListController
{
    public function __invoke(Agent $agent)
    {
        return $agent
            ->texts()
            ->select('id', 'length', 'excerpt', 'status', 'created_at')
            ->latest()
            ->get();
    }
}
