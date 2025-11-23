<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourcesTrainedWebsitesListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $currentAgentWebsiteIds = $agent->websites()->pluck('source_websites.id')->toArray();

        $websites = SourceWebsite::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'ingested')
            ->whereNotIn('id', $currentAgentWebsiteIds)
            ->with(['agents' => function ($query) use ($agent) {
                $query->select('agents.id', 'agents.name')
                    ->where('agents.id', '!=', $agent->id);
            }])
            ->withCount('urls')
            ->get()
            ->map(function (SourceWebsite $website) {
                return [
                    'id' => $website->id,
                    'url' => $website->url,
                    'urls_count' => $website->urls_count,
                    'created_at' => $website->created_at,
                    'agents' => $website->agents->map(function ($agent) {
                        return [
                            'id' => $agent->id,
                            'name' => $agent->name,
                        ];
                    }),
                ];
            });

        return response()->json($websites);
    }
}
