<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourcesLinkWebsiteController
{
    public function __invoke(Request $request, Agent $agent, int $websiteId)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $request->validate([
            'website_id' => ['required', 'exists:source_websites,id'],
        ]);

        $user = $request->user();

        /** @var SourceWebsite $website */
        $website = SourceWebsite::find($websiteId);

        abort_if(
            boolean: $user->cannot('view', $website),
            code: 403,
        );

        // Check if website is already attached to this agent
        if ($agent->websites()->where('source_websites.id', $website->id)->exists()) {
            return response()->json([
                'message' => 'This website is already attached to the agent.',
            ], 422);
        }

        $agent->websites()->attach($website->id);

        return response()->json([
            'message' => 'Website successfully attached to agent.',
        ]);
    }
}
