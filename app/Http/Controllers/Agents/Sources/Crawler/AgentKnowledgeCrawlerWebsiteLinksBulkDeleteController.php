<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\AgentWebsite;
use Illuminate\Http\Request;

class AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController
{
    public function __invoke(Request $request, Agent $agent, AgentWebsite $website)
    {
        $validated = $request->validate([
            'link_ids' => 'required|array',
            'link_ids.*' => 'required|integer|exists:links,id',
        ]);

        // Delete only links that belong to this website
        $website->urls()->whereIn('id', $validated['link_ids'])->delete();

        return response()->json([
            'message' => 'Links deleted successfully.',
            'count' => count($validated['link_ids']),
        ]);
    }
}
