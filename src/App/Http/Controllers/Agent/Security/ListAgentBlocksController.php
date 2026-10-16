<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Security;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Queries\ActiveVisitorBlocksQuery;
use Illuminate\Http\JsonResponse;

/**
 * JSON for the Blocked list (Guardrails "Blocked" tab and Security → Blocked):
 * the workspace's visitor blocks in force (a block covers every agent) and
 * the switched-off chatters who have access to this agent. Loaded when the
 * list opens, so the pages do not carry it. Gated by
 * `agent.permission:agent.guardrails.view` on the route.
 */
class ListAgentBlocksController
{
    /** The most switched-off chatters the list shows. */
    private const int CHATTER_LIMIT = 200;

    public function __invoke(Agent $agent, ActiveVisitorBlocksQuery $blocks): JsonResponse
    {
        return response()->json([
            'visitors' => $blocks->forWorkspace((int) $agent->workspace_id)
                ->map(fn (BlockedVisitor $block): array => $block->toListArray())
                ->values(),
            'chatters' => Chatter::query()
                ->where('workspace_id', $agent->workspace_id)
                ->whereNotNull('deactivated_at')
                ->whereHas('agents', fn ($query) => $query->whereKey($agent->id))
                ->orderByDesc('deactivated_at')
                ->limit(self::CHATTER_LIMIT)
                ->get(['id', 'external_id', 'name', 'deactivation_reason', 'deactivated_at'])
                ->map(fn (Chatter $chatter): array => [
                    'external_id' => $chatter->external_id,
                    'name' => $chatter->name,
                    'reason' => $chatter->deactivation_reason,
                    'deactivated_at' => $chatter->deactivated_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }
}
