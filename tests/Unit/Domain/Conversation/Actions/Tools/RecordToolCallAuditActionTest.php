<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Support\Str;

/**
 * The recorder writes one immutable governance row per tool-call decision into
 * `agent_activity`: who acted (staff or chatter) is stored, secrets are
 * redacted at any depth before anything is stored or capped, oversized
 * payloads are capped, a sha256 of the RAW args is kept, and every outcome
 * the tool loop reports fits the column's CHECK.
 */
function rtcaEvent(array $overrides = []): ToolCallCompleted
{
    return new ToolCallCompleted(...array_merge([
        'agentId' => 1,
        'agentName' => 'My Agent',
        'workspaceId' => 1,
        'userId' => null,
        'userName' => 'Jane',
        'conversationExternalId' => (string) Str::uuid7(),
        'traceId' => (string) Str::uuid7(),
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

it('stores who acted and the redacted arguments with a hash of the raw ones', function (): void {
    $agent = Agent::factory()->create();
    $staff = $agent->workspace->owner;
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $base = ['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id];
    $raw = '{"user":{"name":"Dana","Password":"pw-1"},"items":[{"api_key":"k-1","sku":"A7"}]}';

    app(RecordToolCallAuditAction::class)->execute(rtcaEvent([...$base, 'userId' => $staff->id, 'userName' => $staff->name, 'arguments' => $raw]));
    $staffRow = AgentActivity::query()->latest('id')->first();
    app(RecordToolCallAuditAction::class)->execute(rtcaEvent([...$base, 'chatterId' => $chatter->id, 'userName' => $chatter->name]));
    $chatterRow = AgentActivity::query()->latest('id')->first();

    expect([(int) $staffRow->user_id, $staffRow->chatter_id, $staffRow->user_name])->toBe([$staff->id, null, $staff->name])
        ->and([$chatterRow->user_id, (int) $chatterRow->chatter_id, $chatterRow->user_name])->toBe([null, $chatter->id, $chatter->name])
        ->and($staffRow->arguments)->toEqual([
            'user' => ['name' => 'Dana', 'Password' => '[redacted]'],
            'items' => [['api_key' => '[redacted]', 'sku' => 'A7']],
        ])
        ->and($staffRow->arguments_sha256)->toBe(hash('sha256', $raw))
        ->and($chatterRow->arguments['token'])->toBe('[redacted]');
});

it('caps oversized arguments after redacting them, and an oversized result, flagging both cuts', function (): void {
    config(['chatvia.governance.max_arg_bytes' => 40, 'chatvia.governance.max_result_bytes' => 10]);
    $agent = Agent::factory()->create();
    $raw = json_encode(['token' => 'secret123', 'note' => Str::repeat('x', 200)]);
    $event = rtcaEvent(['agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id, 'arguments' => $raw, 'resultText' => Str::repeat('x', 50)]);

    app(RecordToolCallAuditAction::class)->execute($event);
    $row = AgentActivity::query()->latest('id')->first();

    expect($row->arguments)->toBeNull()
        ->and(strlen((string) $row->arguments_preview))->toBe(40)
        ->and($row->arguments_preview)->toStartWith('{"token":"[redacted]"')
        ->and($row->arguments_preview)->not->toContain('secret123')
        ->and($row->arguments_truncated)->toBeTrue()
        ->and($row->arguments_sha256)->toBe(hash('sha256', $raw))
        ->and(strlen((string) $row->result_text))->toBe(10)
        ->and($row->result_truncated)->toBeTrue();
});

it('stores every outcome the tool loop can report', function (ToolCallOutcome $outcome): void {
    $agent = Agent::factory()->create();
    $event = rtcaEvent([
        'agentId' => $agent->id, 'agentName' => $agent->name, 'workspaceId' => $agent->workspace_id,
        'approvalOutcome' => $outcome->value, 'gated' => $outcome->wasGated(),
    ]);

    app(RecordToolCallAuditAction::class)->execute($event);

    expect(AgentActivity::query()->latest('id')->first()->outcome)->toBe($outcome);
})->with(ToolCallOutcome::cases());
