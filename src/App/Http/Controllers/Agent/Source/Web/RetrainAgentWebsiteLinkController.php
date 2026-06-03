<?php

namespace App\Http\Controllers\Agent\Source\Web;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Url\RetrainAgentWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Request;

/**
 * Re-crawl + re-ingest a single URL inside a trained website.
 */
class RetrainAgentWebsiteLinkController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        SourceWebsite $website,
        AgentWebsiteUrl $link,
        RetrainAgentWebsiteUrlAction $retrain,
    ): void {
        abort_if($request->user()->cannot('delete', $website), 404);

        $retrain->execute($link, $agent);
    }
}
