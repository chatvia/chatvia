<?php

namespace App\Http\Controllers\Agent\Audit;

use App\Http\Controllers\Agent\ChatLog\AgentChatLogController;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
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
        abort_if($request->user()->cannot('view', $agent), 404);

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

        $rows = $query->paginate(25)->withQueryString()->through(fn (AgentActivity $a): array => $a->toAuditArray());

        $connectors = AgentActivity::query()
            ->forAgent($agent->id)
            ->whereNotNull('connector_slug')
            ->select('connector_slug', 'connector_name')
            ->distinct()
            ->orderBy('connector_name')
            ->get()
            ->map(fn (AgentActivity $a): array => ['slug' => (string) $a->connector_slug, 'name' => $a->connector_name])
            ->all();

        return Inertia::render('agents/Audit/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'rows' => $rows,
            'connectors' => $connectors,
            'outcomes' => ['auto', 'approved', 'denied', 'rate_limited'],
            'filters' => [
                'outcome' => $request->filled('outcome') ? (string) $request->string('outcome') : null,
                'connector' => $request->filled('connector') ? (string) $request->string('connector') : null,
            ],
        ]);
    }
}
