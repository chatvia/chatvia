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
        abort_if($request->user()->cannot('update', $website), 404);

        if ($request->recrawl()) {
            $recrawl->execute($website, $agent);
        } else {
            $retrain->execute($website, $agent);
        }

        return back()->with('success', __('Training started successfully'));
    }
}
