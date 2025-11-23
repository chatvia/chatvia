<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourceWebsiteUnlinkController
{
    public function __invoke(Request $request, Agent $agent, int $websiteId)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        /** @var SourceWebsite $website */
        $website = $agent->websites()->where('source_websites.id', $websiteId)->firstOrFail();

        // Detach the website from this agent
        $agent->websites()->detach($website->id);

        return response()->json([
            'message' => 'Website unlinked from agent successfully.',
        ]);
    }
}
