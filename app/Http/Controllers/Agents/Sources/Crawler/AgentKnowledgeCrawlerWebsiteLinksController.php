<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsite;

class AgentKnowledgeCrawlerWebsiteLinksController
{
    public function __invoke(Agent $agent, AgentWebsite $website)
    {
        return $website->links;
    }
}
