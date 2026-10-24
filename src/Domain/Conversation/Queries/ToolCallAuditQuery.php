<?php

declare(strict_types=1);

namespace Domain\Conversation\Queries;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Audit\ToolCallAuditFiltersData;
use Domain\Conversation\Models\AgentActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\LazyCollection;

/**
 * Filtered, newest-first page of the Governed MCP Gateway audit log
 * (`agent_activity`), shared by the account-wide (Settings → Governance) and
 * per-agent Audit viewers. Cursor pagination pairs with the (workspace_id, id)
 * and (agent_id, id) indexes — no COUNT(*)/OFFSET over the unbounded log.
 */
class ToolCallAuditQuery
{
    /**
     * @return CursorPaginator<int, AgentActivity>
     */
    public function forWorkspace(int $workspaceId, ToolCallAuditFiltersData $filters, int $perPage = 25): CursorPaginator
    {
        return $this->filtered($filters)
            ->forWorkspace($workspaceId)
            ->cursorPaginate($perPage);
    }

    /**
     * Every matching tool call of the workspace, newest first, read in chunks
     * for the CSV export.
     *
     * @return LazyCollection<int, AgentActivity>
     */
    public function allForWorkspace(int $workspaceId, ToolCallAuditFiltersData $filters): LazyCollection
    {
        return $this->filtered($filters)
            ->reorder()
            ->forWorkspace($workspaceId)
            ->lazyByIdDesc(500);
    }

    /**
     * @return CursorPaginator<int, AgentActivity>
     */
    public function forAgent(Agent $agent, ToolCallAuditFiltersData $filters, int $perPage = 25): CursorPaginator
    {
        return $this->filtered($filters)
            ->forAgent($agent->id)
            ->cursorPaginate($perPage);
    }

    /**
     * @return Builder<AgentActivity>
     */
    private function filtered(ToolCallAuditFiltersData $filters): Builder
    {
        return AgentActivity::query()
            ->with(['user', 'agent:id,external_id'])
            ->orderByDesc('id')
            ->when($filters->outcome !== null, fn (Builder $query) => $query->where('outcome', $filters->outcome))
            ->when($filters->connector !== null, fn (Builder $query) => $query->where('connector_slug', $filters->connector))
            ->when($filters->agent_id !== null, fn (Builder $query) => $query->where('agent_id', $filters->agent_id));
    }
}
