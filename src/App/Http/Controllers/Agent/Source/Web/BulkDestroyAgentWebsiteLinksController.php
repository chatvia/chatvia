<?php

namespace App\Http\Controllers\Agent\Source\Web;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bulk-delete a set of URLs from a trained website in one call.
 */
class BulkDestroyAgentWebsiteLinksController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        SourceWebsite $website,
    ): JsonResponse {
        abort_if($request->user()->cannot('delete', $website), 404);

        $validated = $request->validate([
            'link_ids' => ['required', 'array'],
            'link_ids.*' => ['required', 'integer', 'exists:source_website_urls,id'],
        ]);

        $count = $website->urls()->whereIn('id', $validated['link_ids'])->delete();

        return response()->json(['message' => 'Links deleted successfully.', 'count' => $count]);
    }
}
