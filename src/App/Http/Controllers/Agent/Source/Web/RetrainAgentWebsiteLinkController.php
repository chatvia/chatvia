<?php

namespace App\Http\Controllers\Agent\Source\Web;

use Domain\Agent\Models\Agent;
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
    ): void {
        abort_if($request->user()->cannot('delete', $website), 404);

        // {link} binds globally — ensure it belongs to {website} so a user
        // can't re-crawl a foreign website's URL.
        abort_if($link->source_website_id !== $website->id, 404);

        $link->train($agent);
    }
}
