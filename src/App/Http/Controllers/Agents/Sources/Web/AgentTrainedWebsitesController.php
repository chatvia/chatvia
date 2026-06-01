<?php

namespace App\Http\Controllers\Agents\Sources\Web;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Actions\Source\AttachWebsiteToAgentAction;
use Domain\Sources\Web\Actions\Source\DetachWebsiteFromAgentAction;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTrainedWebsitesController
{
    public function index(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $currentIds = $agent->websites()->pluck('source_websites.id')->toArray();

        $websites = SourceWebsite::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'ingested')
            ->whereNotIn('id', $currentIds)
            ->with(['agents' => function ($q) use ($agent): void {
                $q->select('agents.id', 'agents.name')
                    ->where('agents.id', '!=', $agent->id);
            }])
            ->withCount('urls')
            ->get()
            ->map(fn (SourceWebsite $website): array => [
                'id' => $website->id,
                'url' => $website->url,
                'urls_count' => $website->urls_count,
                'created_at' => $website->created_at,
                'agents' => $website->agents->map(fn (Agent $a): array => ['id' => $a->id, 'name' => $a->name]),
            ]);

        return response()->json($websites);
    }

    public function store(Request $request, Agent $agent, int $websiteId, AttachWebsiteToAgentAction $attach): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate(['website_id' => ['required', 'exists:source_websites,id']]);

        /** @var SourceWebsite $website */
        $website = SourceWebsite::findOrFail($websiteId);

        abort_if($request->user()->cannot('view', $website), 403);

        if (! $attach->execute($agent, $website)) {
            return response()->json(['message' => 'This website is already attached to the agent.'], 422);
        }

        return response()->json(['message' => 'Website successfully attached to agent.']);
    }

    public function destroy(Request $request, Agent $agent, int $websiteId, DetachWebsiteFromAgentAction $detach): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        /** @var SourceWebsite $website */
        $website = $agent->websites()
            ->where('source_websites.id', $websiteId)
            ->firstOrFail();

        $detach->execute($agent, $website);

        return response()->json(['message' => 'Website unlinked from agent successfully.']);
    }
}
