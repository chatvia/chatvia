<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourceCrawlerLinkRetrainController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website, AgentWebsiteUrl $link)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $website),
            code: 404,
        );

        $link->train($agent->id);
    }
}
