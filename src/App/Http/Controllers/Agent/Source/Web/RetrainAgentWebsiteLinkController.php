<?php

namespace App\Http\Controllers\Agent\Source\Web;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Url\RetrainWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Request;

/**
 * Re-scrapes and re-ingests a single URL inside a trained website.
 */
class RetrainAgentWebsiteLinkController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        SourceWebsite $website,
        AgentWebsiteUrl $link,
        RetrainWebsiteUrlAction $retrain,
    ): void {
        // {website} binds by id inside the workspace: it must also be one of
        // this agent's websites, or a teammate on this agent could change
        // another agent's.
        abort_unless($agent->websites()->whereKey($website->id)->exists(), 404);
        abort_if($request->user()->cannot('delete', $website), 404);

        // {link} binds globally — ensure it belongs to {website} so a user
        // can't re-crawl a foreign website's URL.
        abort_if($link->source_website_id !== $website->id, 404);

        $retrain->execute($link, $agent);
    }
}
