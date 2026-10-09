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
        abort_if($request->user()->cannot('delete', $website), 404);

        $count = $deleteUrls->execute($website, $request->linkIds());

        return response()->json(['message' => __('Links deleted successfully.'), 'count' => $count]);
    }
}
