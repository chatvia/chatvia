<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\CrawlSourceUrlRequest;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Crawl a website and ingest its content. The crawler follows links inside
 * the host (subject to include/exclude path rules) and chunks each page.
 */
#[Group(name: 'Knowledge Sources', weight: 70)]
class SourceUrlController
{
    /**
     * Add URL source
     */
    public function store(CrawlSourceUrlRequest $request, Agent $agent, StartAgentWebsiteCrawlAction $startCrawl): JsonResponse
    {
        // Crawl ownership stays with the agent's owner — the API token may belong
        // to a workspace member but the source file/website ends up on the
        // workspace account.
        /** @var User $owner */
        $owner = $agent->user;

        $website = $startCrawl->execute($agent, $owner, $request->toData());

        return response()->json([
            'data' => [
                'id' => $website->id,
                'kind' => 'url',
                'name' => $website->url,
                'status' => $website->status,
            ],
        ], 201);
    }

    /**
     * Delete URL source
     */
    public function destroy(Request $request, Agent $agent, int $source, DeleteSourceWebsiteAction $deleteWebsite): JsonResponse
    {
        /** @var SourceWebsite $website */
        $website = $agent->websites()->where('source_websites.id', $source)->firstOrFail();

        $deleteWebsite->execute($website);

        return response()->json(null, 204);
    }
}
