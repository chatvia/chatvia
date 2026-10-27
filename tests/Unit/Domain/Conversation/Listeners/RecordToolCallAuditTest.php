<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Models\AgentActivity;
use Illuminate\Support\Str;

/**
 * The queued listener writes a tool call's audit row while the governance
 * audit is on, and nothing while an admin has it switched off. What the row
 * holds is RecordToolCallAuditAction's.
 */
function rtcaListenerEvent(Agent $agent): ToolCallCompleted
{
    return new ToolCallCompleted(
        agentId: $agent->id,
        agentName: $agent->name,
        workspaceId: $agent->workspace_id,
        userId: null,
        userName: null,
        conversationExternalId: (string) Str::uuid7(),
        traceId: (string) Str::uuid7(),
        requestId: 'req_1',
        toolCallId: 'call_1',
        toolName: 'current-weather-tool',
        toolKind: 'mcp',
        connectorSlug: 'weather',
        connectorName: 'Weather',
        arguments: '{"city":"Dubai"}',
        resultText: 'Sunny, 38°C',
        isError: false,
        latencyMs: 42,
        gated: false,
        approvalOutcome: 'auto',
    );
}

it('writes the audit row while the audit is on', function (): void {
    $agent = Agent::factory()->create();

    app(RecordToolCallAudit::class)->handle(rtcaListenerEvent($agent));

    expect(AgentActivity::query()->where('agent_id', $agent->id)->count())->toBe(1);
});

it('writes nothing while the governance audit is switched off', function (): void {
    config(['chatvia.governance.audit_enabled' => false]);
    $agent = Agent::factory()->create();

    app(RecordToolCallAudit::class)->handle(rtcaListenerEvent($agent));

    expect(AgentActivity::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});
