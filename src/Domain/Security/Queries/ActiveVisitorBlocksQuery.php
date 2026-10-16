<?php

declare(strict_types=1);

namespace Domain\Security\Queries;

use Domain\Security\Models\BlockedVisitor;
use Illuminate\Support\Collection;

/**
 * The visitor blocks in force in a workspace, newest first, with what the
 * list shows (agent, country, who blocked). Read by the Security page and by
 * each agent's Guardrails "Blocked" tab: a block applies to every agent of
 * the workspace, so both show the same list.
 */
class ActiveVisitorBlocksQuery
{
    /** The most blocks a page lists. */
    private const int LIMIT = 200;

    /**
     * @return Collection<int, BlockedVisitor>
     */
    public function forWorkspace(int $workspaceId): Collection
    {
        return BlockedVisitor::query()
            ->where('workspace_id', $workspaceId)
            ->active()
            ->with(['agent:id,name', 'country', 'blockedBy:id,name'])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
