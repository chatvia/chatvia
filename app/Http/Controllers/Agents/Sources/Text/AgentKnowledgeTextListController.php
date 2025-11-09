<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;

class AgentKnowledgeTextListController
{
    public function __invoke(Agent $agent)
    {
        return $agent
            ->texts()
            ->select('id', 'length', 'status', 'created_at')
            ->selectRaw("CONCAT(SUBSTRING(content, 1, 200), '...') as excerpt")
            ->latest()
            ->get();
    }
}
