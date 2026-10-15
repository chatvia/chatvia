<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Support\Str;

/**
 * The recorder writes one immutable governance row per tool-call decision into
 * `agent_activity`. The guarantees under test: denormalized agent/user names +
 * connector are stored, secrets are redacted, oversized payloads are capped,
 * and a sha256 of the RAW args is kept.
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

it('writes one agent_activity row with denormalized names, redacted args + raw hash', function (): void {
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

it('stores who acted: the staff account or the chatter named on the event', function (): void {
    $agent = Agent::factory()->create();
    $staff = $agent->workspace->owner;
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $base = ['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id];

    app(RecordToolCallAuditAction::class)->execute(auditEvent([...$base, 'userId' => $staff->id, 'userName' => $staff->name]));
    $staffRow = AgentActivity::query()->latest('id')->first();

    app(RecordToolCallAuditAction::class)->execute(auditEvent([...$base, 'chatterId' => $chatter->id, 'userName' => $chatter->name]));
    $chatterRow = AgentActivity::query()->latest('id')->first();

    expect([(int) $staffRow->user_id, $staffRow->chatter_id, $staffRow->user_name])->toBe([$staff->id, null, $staff->name])
        ->and([$chatterRow->user_id, (int) $chatterRow->chatter_id, $chatterRow->user_name])->toBe([null, $chatter->id, $chatter->name]);
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

it('keeps what the person said when they approved out loud, capped', function (): void {
    $agent = Agent::factory()->create();
    $base = ['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id, 'approvalOutcome' => 'approved', 'gated' => true];

    app(RecordToolCallAuditAction::class)->execute(auditEvent([...$base, 'decisionNote' => 'Yes, send it.']));
    expect(AgentActivity::query()->latest('id')->first()->toAuditArray()['decision_note'])->toBe('Yes, send it.');

    app(RecordToolCallAuditAction::class)->execute(auditEvent([...$base, 'decisionNote' => str_repeat('y', 5000)]));
    expect(strlen((string) AgentActivity::query()->latest('id')->first()->decision_note))->toBe(1000);
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

it('redacts secret keys at any depth and in any case', function (): void {
    $agent = Agent::factory()->create();
    $raw = '{"user":{"name":"Dana","Password":"pw-1"},"items":[{"api_key":"k-1","sku":"A7"}]}';
    $event = auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id, 'arguments' => $raw]);

    app(RecordToolCallAuditAction::class)->execute($event);
    $row = AgentActivity::query()->latest('id')->first();

    // jsonb keeps its own key order, so the keys are compared without it.
    expect($row->arguments)->toEqual([
        'user' => ['name' => 'Dana', 'Password' => '[redacted]'],
        'items' => [['api_key' => '[redacted]', 'sku' => 'A7']],
    ])
        ->and($row->arguments_truncated)->toBeFalse()
        ->and($row->arguments_sha256)->toBe(hash('sha256', $raw));
});

it('caps oversized arguments after redacting them and flags the cut', function (): void {
    config(['chatvia.governance.max_arg_bytes' => 40]);
    $agent = Agent::factory()->create();
    $raw = json_encode(['token' => 'secret123', 'note' => str_repeat('x', 200)]);
    $event = auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id, 'arguments' => $raw]);

    app(RecordToolCallAuditAction::class)->execute($event);
    $row = AgentActivity::query()->latest('id')->first();

    expect($row->arguments)->toBeString()
        ->and(strlen($row->arguments))->toBe(40)
        ->and($row->arguments)->toStartWith('{"token":"[redacted]"')
        ->and($row->arguments)->not->toContain('secret123')
        ->and($row->arguments_truncated)->toBeTrue()
        ->and($row->arguments_sha256)->toBe(hash('sha256', $raw));
});

it('stores arguments that are not JSON as text, capped', function (): void {
    config(['chatvia.governance.max_arg_bytes' => 10]);
    $agent = Agent::factory()->create();
    $base = ['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id];

    app(RecordToolCallAuditAction::class)->execute(auditEvent([...$base, 'arguments' => 'city=Oslo']));
    $short = AgentActivity::query()->latest('id')->first();

    app(RecordToolCallAuditAction::class)->execute(auditEvent([...$base, 'arguments' => 'city=Copenhagen']));
    $long = AgentActivity::query()->latest('id')->first();

    expect([$short->arguments, $short->arguments_truncated])->toBe(['city=Oslo', false])
        ->and([$long->arguments, $long->arguments_truncated])->toBe(['city=Copen', true]);
});

it('writes the audit row from the queued listener while the audit is on', function (): void {
    $agent = Agent::factory()->create();

    app(RecordToolCallAudit::class)->handle(auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id]));

    expect(AgentActivity::query()->where('agent_id', $agent->id)->count())->toBe(1);
});

it('writes nothing while the governance audit is switched off', function (): void {
    config(['chatvia.governance.audit_enabled' => false]);
    $agent = Agent::factory()->create();

    app(RecordToolCallAudit::class)->handle(auditEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id]));

    expect(AgentActivity::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});
