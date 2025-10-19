<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsite;
use Illuminate\Http\Request;

class AgentKnowledgeCrawlerWebsiteLinksController
{
    public function __invoke(Request $request, Agent $agent, AgentWebsite $website)
    {
        $query = $website->urls();

        // Apply search filter if q parameter is provided
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where('url', 'like', '%'.$searchTerm.'%');
        }

        return $query->simplePaginate(50);
    }
}
