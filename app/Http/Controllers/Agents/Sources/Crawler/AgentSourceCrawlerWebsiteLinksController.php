<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourceCrawlerWebsiteLinksController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $query = $website->urls()->select([
            'id',
            'source_website_id',
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
