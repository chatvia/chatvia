<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

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

        $validated = $request->validate([
            'recrawl' => ['nullable', 'boolean'],
        ]);

        if ($validated['recrawl'] ?? false) {
            // Re-crawl the website to discover new links
            $website->startCrawling();
        } else {
            // Just retrain existing links
            $website->startTraining();
        }

        return back()->with('success', 'Training started successfully');
    }
}
