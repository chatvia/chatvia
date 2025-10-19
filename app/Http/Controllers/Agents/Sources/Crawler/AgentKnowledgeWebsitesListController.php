<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeWebsitesListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return $agent
            ->websites()
            ->select(['id', 'url', 'status', 'created_at', 'options'])
            ->withCount('urls')
            ->get();
    }
}
