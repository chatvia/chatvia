<?php

namespace App\Http\Controllers\Agents\Knowledge\Crawler;

use App\Data\Crawler\LinkData;
use App\Models\Agent;
use App\Models\AgentWebsite;
use Illuminate\Http\Request;

class AgentKnowledgeCrawlerWebsiteTrainController
{
    public function __invoke(Request $request, Agent $agent, AgentWebsite $website)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $website),
            code: 404,
        );

        $request->validate([
            'links' => ['required', 'array'],
            'links.*' => ['required', 'url'],
        ]);

        /** @var array<LinkData> $websiteLinks */
        $websiteLinks = LinkData::collect($website->links);
        $requestLinks = $request->input('links');

        $filteredWebsiteLinks = collect($websiteLinks)
            ->filter(fn (LinkData $linkData) => in_array($linkData->link, $requestLinks, true))
            ->values()
            ->toArray();

        $website->update(['links' => $filteredWebsiteLinks]);

        $website->startTraining();

        return back()->with('success', 'Training started successfully');
    }
}
