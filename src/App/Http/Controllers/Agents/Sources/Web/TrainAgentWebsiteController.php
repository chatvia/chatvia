<?php

namespace App\Http\Controllers\Agents\Sources\Web;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Actions\RetrainSourceWebsiteAction;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kick off training for a website source — optionally re-crawling its URLs
 * first (`recrawl=true`) before re-ingesting.
 */
class TrainAgentWebsiteController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website, RetrainSourceWebsiteAction $retrain): RedirectResponse
    {
        abort_if($request->user()->cannot('update', $website), 404);

        $validated = $request->validate(['recrawl' => ['nullable', 'boolean']]);

        $retrain->execute($website, $agent, (bool) ($validated['recrawl'] ?? false));

        return back()->with('success', 'Training started successfully');
    }
}
