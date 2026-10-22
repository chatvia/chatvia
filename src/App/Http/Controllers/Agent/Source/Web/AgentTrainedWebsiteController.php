<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Http\Requests\Agent\Source\Web\LinkSourceWebsiteRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\AttachSourceToAgentAction;
use Domain\Knowledge\Source\Actions\DetachSourceFromAgentAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;

/**
 * The shared website pool of a workspace: lists trained websites an agent does
 * not use yet, and links or unlinks one. Agent access is checked by the route
 * middleware; access to the website by its policy.
 */
class AgentTrainedWebsiteController
{
    public function index(Agent $agent): JsonResponse
    {
        $websites = SourceWebsite::query()
            // Websites belong to the workspace (the agent's owner), not to the
            // teammate who is looking.
            ->where('workspace_id', $agent->workspace_id)
            ->where('status', SourceStatus::Completed)
            ->whereDoesntHave('agents', fn ($query) => $query->where('agents.id', $agent->id))
            ->with(['agents' => fn ($query) => $query->select('agents.id', 'agents.name')->where('agents.id', '!=', $agent->id)])
            // Only the columns the picker shows; set before withCount, which
            // adds the count to this list.
            ->select(['id', 'url', 'created_at'])
            ->withCount('urls')
            ->get()
            ->map(fn (SourceWebsite $website): array => [
                'id' => $website->id,
                'url' => $website->url,
                'urls_count' => $website->urls_count,
                'created_at' => $website->created_at,
                'agents' => $website->agents->map(fn (Agent $other): array => ['id' => $other->id, 'name' => $other->name]),
            ]);

        return response()->json($websites);
    }

    public function store(LinkSourceWebsiteRequest $request, Agent $agent, int $websiteId, AttachSourceToAgentAction $attach): JsonResponse
    {
        // Another workspace's website answers like an unknown id.
        $website = SourceWebsite::query()->where('workspace_id', $agent->workspace_id)->findOrFail($websiteId);

        abort_if($request->user()->cannot('view', $website), 403);

        if (! $attach->execute($agent, $website)) {
            return response()->json(['message' => __('This website is already attached to the agent.')], 422);
        }

        return response()->json(['message' => __('Website successfully attached to agent.')]);
    }

    public function destroy(Agent $agent, int $websiteId, DetachSourceFromAgentAction $detach): JsonResponse
    {
        /** @var SourceWebsite $website */
        $website = $agent->websites()->where('source_websites.id', $websiteId)->firstOrFail();

        $detach->execute($agent, $website);

        return response()->json(['message' => __('Website unlinked from agent successfully.')]);
    }
}
