<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Security;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Security\Data\BlockedVisitorRowData;
use Domain\Security\Data\BlockListResourceData;
use Domain\Security\Data\DeactivatedChatterRowData;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Queries\ActiveVisitorBlocksQuery;
use Domain\Security\Queries\DeactivatedChattersQuery;
use Spatie\LaravelData\DataCollection;

/**
 * JSON for the Blocked list (Guardrails "Blocked" tab and Security → Blocked):
 * the workspace's visitor blocks in force (a block covers every agent) and
 * the switched-off chatters who have access to this agent. Loaded when the
 * list opens, so the pages do not carry it. Gated by
 * `agent.permission:agent.guardrails.view` on the route.
 */
class ListAgentBlocksController
{
    public function __invoke(Agent $agent, ActiveVisitorBlocksQuery $blocks, DeactivatedChattersQuery $chatters): BlockListResourceData
    {
        $visitors = $blocks->forWorkspace((int) $agent->workspace_id)
            ->map(fn (BlockedVisitor $block): BlockedVisitorRowData => BlockedVisitorRowData::fromModel($block))
            ->values();

        $deactivated = $chatters->forAgent($agent)
            ->map(fn (Chatter $chatter): DeactivatedChatterRowData => DeactivatedChatterRowData::fromModel($chatter))
            ->values();

        return new BlockListResourceData(
            visitors: BlockedVisitorRowData::collect($visitors, DataCollection::class),
            chatters: DeactivatedChatterRowData::collect($deactivated, DataCollection::class),
        );
    }
}
