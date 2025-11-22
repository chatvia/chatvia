<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourceWebsiteDeleteController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $website),
            code: 404,
        );

        $website->delete();
    }
}
