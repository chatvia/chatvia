<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsite;
use Illuminate\Http\Request;

class AgentSourceCrawlerWebsiteLinksController
{
    public function __invoke(Request $request, Agent $agent, AgentWebsite $website)
    {
        $query = $website->urls()->select([
            'id',
            'agent_website_id',
            'url',
            'status',
            'created_at',
            'crawled_at',
        ]);

        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where('url', 'like', '%'.$searchTerm.'%');
        }

        return $query->latest('crawled_at')->simplePaginate(50);
    }
}
