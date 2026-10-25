<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Http\Requests\Agent\Source\Web\BulkDestroyWebsiteLinksRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlsAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;

/**
 * Removes several pages from a trained website in one call, each with the
 * chunks only it produced.
 */
class BulkDestroyAgentWebsiteLinksController
{
    public function __invoke(
        BulkDestroyWebsiteLinksRequest $request,
        Agent $agent,
        SourceWebsite $website,
        DeleteAgentWebsiteUrlsAction $deleteUrls,
    ): JsonResponse {
        // {website} binds by id inside the workspace: it must also be one of
        // this agent's websites, or a teammate on this agent could change
        // another agent's.
        abort_unless($agent->websites()->whereKey($website->id)->exists(), 404);
        abort_if($request->user()->cannot('delete', $website), 404);

        $count = $deleteUrls->execute($website, $request->linkIds());

        return response()->json(['message' => __('Links deleted successfully.'), 'count' => $count]);
    }
}
