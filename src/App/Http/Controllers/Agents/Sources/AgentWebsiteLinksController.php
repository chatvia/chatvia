<?php

namespace App\Http\Controllers\Agents\Sources;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\AgentWebsiteUrl;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentWebsiteLinksController
{
    public function index(Request $request, Agent $agent, SourceWebsite $website): Paginator
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $query = $website->urls()->select([
            'id',
            'source_website_id',
            'url',
            'status',
            'created_at',
            'crawled_at',
        ]);

        if ($request->filled('q')) {
            $query->where('url', 'like', '%'.$request->input('q').'%');
        }

        return $query->latest('crawled_at')->simplePaginate(50);
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website, AgentWebsiteUrl $link): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);

        $link->chunks()->delete();
        $link->delete();
    }

    public function retrain(Request $request, Agent $agent, SourceWebsite $website, AgentWebsiteUrl $link): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);

        $link->train($agent);
    }

    public function bulkDestroy(Request $request, Agent $agent, SourceWebsite $website): JsonResponse
    {
        abort_if($request->user()->cannot('delete', $website), 404);

        $validated = $request->validate([
            'link_ids' => ['required', 'array'],
            'link_ids.*' => ['required', 'integer', 'exists:source_website_urls,id'],
        ]);

        $website->urls()
            ->whereIn('id', $validated['link_ids'])
            ->delete();

        return response()->json([
            'message' => 'Links deleted successfully.',
            'count' => count($validated['link_ids']),
        ]);
    }
}
