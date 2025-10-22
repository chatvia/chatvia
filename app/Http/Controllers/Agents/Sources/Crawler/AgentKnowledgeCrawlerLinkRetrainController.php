<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsite;
use App\Models\AgentWebsiteUrl;
use Illuminate\Http\Request;

class AgentKnowledgeCrawlerLinkRetrainController
{
    public function __invoke(Request $request, Agent $agent, AgentWebsite $website, AgentWebsiteUrl $link)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $website),
            code: 404,
        );

        $link->train();
    }
}
