<?php

namespace App\Http\Controllers\Settings\Governance;

use App\Http\Controllers\Agent\Audit\AgentAuditController;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Governed MCP Gateway audit log — a read-only, workspace-scoped view of
 * every tool call an agent made (auto / approved / denied / rate-limited),
 * across ALL of the workspace's agents. Gated by the `account.governance` team
 * permission. The per-agent view lives at {@see AgentAuditController}.
 */
class GovernanceAuditController
{
    public function __invoke(Request $request): Response
    {
        $workspaceId = $request->user()->workspace()->id;

        $query = AgentActivity::query()
            ->forWorkspace($workspaceId)
            ->with(['user', 'agent:id,external_id'])
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        // Cursor pagination pairs with the (workspace_id, id) index — no COUNT(*)/OFFSET over the unbounded log.
        $rows = $query->cursorPaginate(25)->withQueryString()->through(fn (AgentActivity $a): array => $a->toAuditArray());

        $agents = Agent::query()
            ->where('user_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Agent $a): array => ['id' => $a->id, 'name' => $a->name])
            ->all();

        return Inertia::render('workspace/governance/Governance', [
            'rows' => $rows,
            'agents' => $agents,
            'connectors' => $this->connectorOptions($workspaceId),
            'outcomes' => array_column(ToolCallOutcome::cases(), 'value'),
            'filters' => [
                'outcome' => $request->filled('outcome') ? (string) $request->string('outcome') : null,
                'connector' => $request->filled('connector') ? (string) $request->string('connector') : null,
                'agent_id' => $request->filled('agent_id') ? $request->integer('agent_id') : null,
            ],
        ]);
    }

    /**
     * @param  Builder<AgentActivity>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('outcome')) {
            $query->where('outcome', (string) $request->string('outcome'));
        }
        if ($request->filled('connector')) {
            $query->where('connector_slug', (string) $request->string('connector'));
        }
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }
    }

    /**
     * Filter options from the workspace's configured MCP servers. A deleted
     * connector drops out of the dropdown; its rows stay reachable unfiltered.
     *
     * @return array<int, array{slug: string, name: ?string}>
     */
    private function connectorOptions(int $workspaceId): array
    {
        return AgentMcpServer::query()
            ->where('user_id', $workspaceId)
            ->whereNotNull('connector_slug')
            ->select('connector_slug', 'name')
            ->orderBy('name')
            ->get()
            ->unique('connector_slug')
            ->map(fn (AgentMcpServer $s): array => ['slug' => (string) $s->connector_slug, 'name' => $s->name])
            ->values()
            ->all();
    }
}
