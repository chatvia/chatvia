<?php

namespace App\Http\Controllers\Agents\Knowledge\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsite;
use Illuminate\Http\Request;

class AgentKnowledgeWebsiteDeleteController
{
    public function __invoke(Request $request, Agent $agent, AgentWebsite $website)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $website),
            code: 404,
        );

        $website->delete();
    }
}
