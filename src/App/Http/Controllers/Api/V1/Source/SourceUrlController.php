<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\CrawlSourceUrlRequest;
use App\Http\Resources\Api\V1\Source\SourceData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;

/**
 * Crawl a website and ingest its content. The crawler follows links inside
 * the host (subject to include/exclude path rules) and chunks each page.
 */
#[Group(name: 'Sources', weight: 70)]
class SourceUrlController
{
    /**
     * Add URL source
     */
    public function store(CrawlSourceUrlRequest $request, Agent $agent, StartAgentWebsiteCrawlAction $startCrawl): JsonResponse
    {
        // The website is keyed to the agent's owner (the workspace account),
        // even when the API token belongs to a workspace member.
        $website = $startCrawl->execute($agent, $request->toData());

        return response()->json(['data' => SourceData::fromModel($website)], 201);
    }

    /**
     * Delete URL source
     */
    public function destroy(Agent $agent, SourceWebsite $website, DeleteSourceWebsiteAction $deleteWebsite): JsonResponse
    {
        // {website} is looked up through the agent's own websites, so a
        // website of another agent is a 404.
        $deleteWebsite->execute($website);

        return response()->json(null, 204);
    }
}
