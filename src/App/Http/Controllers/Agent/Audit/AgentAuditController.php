<?php

namespace App\Http\Controllers\Agent\Audit;

use App\Http\Controllers\Agent\ChatLog\AgentChatLogController;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\Capability;
use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Mcp\Models\AgentMcpServer;
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
    public function __invoke(Request $request, Agent $agent): Response
    {
        // The audit log is a Pro-tier capability. Below it the page still renders
        // so the Vue LockedFeature card can show the upgrade prompt, but we skip
        // the queries and ship no audit data.
        if (! $request->user()->hasCapability(Capability::Audit)) {
            return Inertia::render('agents/Audit/index', [
                'agent' => AgentResourceData::fromModel($agent),
                'rows' => ['data' => []],
                'connectors' => [],
                'outcomes' => array_column(ToolCallOutcome::cases(), 'value'),
                'filters' => ['outcome' => null, 'connector' => null],
            ]);
        }

        $query = AgentActivity::query()
            ->forAgent($agent->id)
            ->with(['user', 'agent:id,external_id'])
            ->orderByDesc('id');

        if ($request->filled('outcome')) {
            $query->where('outcome', (string) $request->string('outcome'));
        }
        if ($request->filled('connector')) {
            $query->where('connector_slug', (string) $request->string('connector'));
        }

        // Cursor pagination pairs with the (agent_id, id) index.
        $rows = $query->cursorPaginate(25)->withQueryString()->through(fn (AgentActivity $a): array => $a->toAuditArray());

        // Filter options from the workspace's configured connectors.
        $connectors = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->whereNotNull('connector_slug')
            ->select('connector_slug', 'name')
            ->orderBy('name')
            ->get()
            ->unique('connector_slug')
            ->map(fn (AgentMcpServer $s): array => ['slug' => (string) $s->connector_slug, 'name' => $s->name])
            ->values()
            ->all();

        return Inertia::render('agents/Audit/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'rows' => $rows,
            'connectors' => $connectors,
            'outcomes' => array_column(ToolCallOutcome::cases(), 'value'),
            'filters' => [
                'outcome' => $request->filled('outcome') ? (string) $request->string('outcome') : null,
                'connector' => $request->filled('connector') ? (string) $request->string('connector') : null,
            ],
        ]);
    }
}
