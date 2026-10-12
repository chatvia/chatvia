<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Support\Str;

/**
 * The recorder writes one immutable governance row per tool-call decision into
 * `agent_activity`. The guarantees under test: denormalized agent/user names +
 * connector are stored, the tool name is unprefixed, secrets are redacted,
 * oversized payloads are capped, and a sha256 of the RAW args is kept.
 *
 * REQUIRES the migrated test DB (the agent_activity table).
 */
function auditEvent(array $overrides = []): ToolCallCompleted
{
    return new ToolCallCompleted(...array_merge([
        'agentId' => 1,
        'agentName' => 'My Agent',
        'workspaceId' => 1,
        'userId' => null,
        'userName' => 'Jane',
        'conversationExternalId' => (string) Str::uuid(),
        'traceId' => (string) Str::uuid(),
        'requestId' => 'req_1',
        'toolCallId' => 'call_1',
        'toolName' => 'current-weather-tool',
        'toolKind' => 'mcp',
        'connectorSlug' => 'weather',
        'connectorName' => 'Weather',
        'arguments' => '{"city":"Dubai","token":"secret123"}',
        'resultText' => 'Sunny, 38°C',
        'isError' => false,
        'latencyMs' => 42,
        'gated' => false,
        'approvalOutcome' => 'auto',
    ], $overrides));
}

it('writes one agent_activity row with denormalized names, unprefixed tool, redacted args + raw hash', function (): void {
    $agent = Agent::factory()->create();
    $event = auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id]);

    app(RecordToolCallAuditAction::class)->execute($event);

    $row = AgentActivity::query()->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->outcome)->toBe('auto')
        ->and((int) $row->agent_id)->toBe($agent->id)
        ->and($row->agent_name)->toBe($agent->name)
        ->and((int) $row->workspace_id)->toBe((int) $agent->workspace_id)
        ->and($row->user_name)->toBe('Jane')
        ->and($row->tool_name)->toBe('current-weather-tool')
        ->and($row->connector_slug)->toBe('weather')
        ->and($row->connector_name)->toBe('Weather')
        ->and($row->arguments['city'])->toBe('Dubai')
        ->and($row->arguments['token'])->toBe('[redacted]')   // secret never stored
        ->and($row->arguments_sha256)->toBe(hash('sha256', $event->arguments))
        ->and((int) $row->latency_ms)->toBe(42);
});

it('marks an anonymous visitor (no user)', function (): void {
    $agent = Agent::factory()->create();
    $event = auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id, 'userId' => null, 'userName' => null]);

    app(RecordToolCallAuditAction::class)->execute($event);

    expect(AgentActivity::query()->latest('id')->first()->user_name)->toBeNull();
});

it('records decided_at for a gated approved call', function (): void {
    $agent = Agent::factory()->create();
    $event = auditEvent([
        'agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id,
        'approvalOutcome' => 'approved', 'gated' => true, 'decidedAt' => now()->toIso8601String(),
    ]);

    app(RecordToolCallAuditAction::class)->execute($event);
    $row = AgentActivity::query()->latest('id')->first();

    expect($row->outcome)->toBe('approved')
        ->and($row->gated)->toBeTrue()
        ->and($row->decided_at)->not->toBeNull();
});

it('caps an oversized result and flags truncation', function (): void {
    config(['chatvia.governance.max_result_bytes' => 10]);
    $agent = Agent::factory()->create();
    $event = auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id, 'resultText' => str_repeat('x', 50)]);

    app(RecordToolCallAuditAction::class)->execute($event);
    $row = AgentActivity::query()->latest('id')->first();

    expect(strlen((string) $row->result_text))->toBe(10)
        ->and($row->result_truncated)->toBeTrue();
});

it('stores every outcome the tool loop can report', function (ToolCallOutcome $outcome): void {
    // agent_activity.outcome has a Postgres CHECK: an enum case it does not
    // list would make the queued listener fail and the audit row go missing.
    $agent = Agent::factory()->create();
    $event = auditEvent([
        'agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id,
        'approvalOutcome' => $outcome->value, 'gated' => $outcome->wasGated(),
    ]);

    app(RecordToolCallAuditAction::class)->execute($event);

    expect(AgentActivity::query()->latest('id')->first()->outcome)->toBe($outcome->value);
})->with(ToolCallOutcome::cases());
