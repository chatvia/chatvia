<?php

namespace App\Http\Controllers\Settings\Governance;

use App\Http\Controllers\Agent\Audit\AgentAuditController;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
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

        $rows = $query->paginate(25)->withQueryString()->through(fn (AgentActivity $a): array => $a->toAuditArray());

        $agents = Agent::query()
            ->where('user_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Agent $a): array => ['id' => $a->id, 'name' => $a->name])
            ->all();

        return Inertia::render('settings/Governance', [
            'rows' => $rows,
            'agents' => $agents,
            'connectors' => $this->connectorOptions($workspaceId),
            'outcomes' => ['auto', 'approved', 'denied', 'rate_limited'],
            'filters' => [
                'outcome' => $request->filled('outcome') ? (string) $request->string('outcome') : null,
                'connector' => $request->filled('connector') ? (string) $request->string('connector') : null,
                'agent_id' => $request->filled('agent_id') ? $request->integer('agent_id') : null,
            ],
        ]);
    }

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
     * Distinct connectors that appear in this workspace's audit, for the filter.
     *
     * @return array<int, array{slug: string, name: ?string}>
     */
    private function connectorOptions(int $workspaceId): array
    {
        return AgentActivity::query()
            ->forWorkspace($workspaceId)
            ->whereNotNull('connector_slug')
            ->select('connector_slug', 'connector_name')
            ->distinct()
            ->orderBy('connector_name')
            ->get()
            ->map(fn (AgentActivity $a): array => ['slug' => (string) $a->connector_slug, 'name' => $a->connector_name])
            ->all();
    }
}
