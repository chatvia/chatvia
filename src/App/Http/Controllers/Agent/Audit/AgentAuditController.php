<?php

namespace App\Http\Controllers\Agent\Audit;

use App\Http\Controllers\Agent\ChatLog\AgentChatLogController;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
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
 * Per-agent tool-call audit log (Governed MCP Gateway) — the same data as the
 * account-wide Settings → Governance page, scoped to a single agent and gated by
 * the `agent.audit.view` permission. Mirrors {@see AgentChatLogController}.
 */
class AgentAuditController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        ToolCallAuditQuery $audit,
        ConnectorFilterOptionsQuery $connectors,
    ): Response {
        $filters = new ToolCallAuditFiltersData(
            outcome: $request->filled('outcome') ? (string) $request->string('outcome') : null,
            connector: $request->filled('connector') ? (string) $request->string('connector') : null,
        );

        $rows = $audit->forAgent($agent, $filters)
            ->withQueryString()
            ->through(fn (AgentActivity $activity): ToolCallAuditRowData => ToolCallAuditRowData::fromModel($activity));

        return Inertia::render('dashboard/agents/pages/Audit/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'rows' => $rows,
            'connectors' => $connectors->forWorkspace($agent->workspace_id),
            'outcomes' => array_column(ToolCallOutcome::cases(), 'value'),
            'filters' => $filters,
        ]);
    }
}
