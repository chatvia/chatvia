<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourceCrawlerWebsiteLinksBulkDeleteController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $website),
            code: 404,
        );

        $validated = $request->validate([
            'link_ids' => ['required', 'array'],
            'link_ids.*' => ['required', 'integer', 'exists:source_website_urls,id'],
        ]);

        $website
            ->urls()
            ->whereIn('id', $validated['link_ids'])
            ->delete();

        return response()->json([
            'message' => 'Links deleted successfully.',
            'count' => count($validated['link_ids']),
        ]);
    }
}
