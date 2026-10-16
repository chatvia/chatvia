<?php

namespace App\Http\Controllers\Settings\Governance;

use App\Http\Controllers\Agent\Audit\AgentAuditController;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Audit\ToolCallAuditFiltersData;
use Domain\Conversation\Data\Audit\ToolCallAuditRowData;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Queries\ToolCallAuditQuery;
use Domain\Integration\Mcp\Queries\ConnectorFilterOptionsQuery;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Governed MCP Gateway audit log — a read-only, workspace-scoped view of
 * every tool call an agent made (one row per ToolCallOutcome), across ALL of
 * the workspace's agents. Gated by the `account.governance` team
 * permission. The per-agent view lives at {@see AgentAuditController}.
 */
class GovernanceAuditController
{
    public function __invoke(
        Request $request,
        ToolCallAuditQuery $audit,
        ConnectorFilterOptionsQuery $connectors,
    ): Response {
        $workspaceId = $request->user()->workspace()->id;

        $filters = new ToolCallAuditFiltersData(
            outcome: $request->filled('outcome') ? (string) $request->string('outcome') : null,
            connector: $request->filled('connector') ? (string) $request->string('connector') : null,
            agent_id: $request->filled('agent_id') ? $request->integer('agent_id') : null,
        );

        $rows = $audit->forWorkspace($workspaceId, $filters)
            ->withQueryString()
            ->through(fn (AgentActivity $activity): ToolCallAuditRowData => ToolCallAuditRowData::fromModel($activity));

        $agents = Agent::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Agent $a): array => ['id' => $a->id, 'name' => $a->name])
            ->all();

        return Inertia::render('workspace/governance/Governance', [
            'rows' => $rows,
            'agents' => $agents,
            'connectors' => $connectors->forWorkspace($workspaceId),
            'outcomes' => array_column(ToolCallOutcome::cases(), 'value'),
            'filters' => $filters,
        ]);
    }
}
