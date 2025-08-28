<?php

namespace App\Http\Controllers\Agents\Knowledge\Crawler;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeWebsitesListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return $agent
            ->websites()
            ->select(['id', 'url', 'status', 'length', 'created_at', 'options'])
            ->get();
    }
}
