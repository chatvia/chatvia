<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentSourceWebsitesListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $l = $agent
            ->websites()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at', 'options', 'expected_chunks'])
            ->withCount('urls')
            ->withCount('agents')
            ->get();

        return collect($l)->map(function ($website) {
            $website['progress'] = $website->ingestionProgress();

            return $website;
        });
    }
}
