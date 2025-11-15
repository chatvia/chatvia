<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentSourceWebsitesListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $l = $agent
            ->websites()
            ->select(['id', 'url', 'status', 'created_at', 'options', 'expected_chunks'])
            ->withCount('urls')
            ->get();

        return collect($l)->map(function ($website) {
            $website['progress'] = $website->ingestionProgress();

            return $website;
        });
    }
}
