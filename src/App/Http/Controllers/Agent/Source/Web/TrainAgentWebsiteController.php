<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Http\Requests\Agent\Source\Web\TrainAgentWebsiteRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Source\RecrawlSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Actions\Source\RetrainSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\RedirectResponse;

/**
 * Starts training a website source — optionally discovering its URLs again
 * first (`recrawl=true`).
 */
class TrainAgentWebsiteController
{
    public function __invoke(
        TrainAgentWebsiteRequest $request,
        Agent $agent,
        SourceWebsite $website,
        RecrawlSourceWebsiteAction $recrawl,
        RetrainSourceWebsiteAction $retrain,
    ): RedirectResponse {
        // {website} binds by id inside the workspace: it must also be one of
        // this agent's websites, or a teammate on this agent could change
        // another agent's.
        abort_unless($agent->websites()->whereKey($website->id)->exists(), 404);
        abort_if($request->user()->cannot('update', $website), 404);

        if ($request->recrawl()) {
            $recrawl->execute($website, $agent);
        } else {
            $retrain->execute($website, $agent);
        }

        return back()->with('success', __('Training started successfully'));
    }
}
