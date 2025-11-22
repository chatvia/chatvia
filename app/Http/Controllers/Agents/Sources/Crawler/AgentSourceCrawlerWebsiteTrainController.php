<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;

class AgentSourceCrawlerWebsiteTrainController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website)
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
            $website->startCrawling($agent->id);
        } else {
            // Just retrain existing links
            $website->startTraining($agent->id);
        }

        return back()->with('success', 'Training started successfully');
    }
}
