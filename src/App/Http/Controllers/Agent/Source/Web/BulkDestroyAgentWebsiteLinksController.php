<?php

namespace App\Http\Controllers\Agent\Source\Web;

use Domain\Agent\Models\Agent;
use Domain\Source\Web\Actions\Url\BulkDeleteWebsiteUrlsAction;
use Domain\Source\Web\Models\SourceWebsite;
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
        BulkDeleteWebsiteUrlsAction $bulkDelete,
    ): JsonResponse {
        abort_if($request->user()->cannot('delete', $website), 404);

        $validated = $request->validate([
            'link_ids' => ['required', 'array'],
            'link_ids.*' => ['required', 'integer', 'exists:source_website_urls,id'],
        ]);

        $count = $bulkDelete->execute($website, $validated['link_ids']);

        return response()->json(['message' => 'Links deleted successfully.', 'count' => $count]);
    }
}
