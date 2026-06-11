<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\RecordGuardrailEventAction;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Models\GuardrailEvent;

/**
 * The recorder is the safety log's single writer. Its contract: persist the
 * denormalized identity + verdict, hash the FULL original sample, and store only
 * a size-capped snippet so the log can never itself become a PII sink.
 */
function guardEvent(Agent $agent, string $sample, array $overrides = []): GuardrailTriggered
{
    return new GuardrailTriggered(
        agentId: $overrides['agentId'] ?? $agent->id,
        agentName: $agent->name,
        workspaceId: (int) $agent->user_id,
        userId: $overrides['userId'] ?? null,
        userName: $overrides['userName'] ?? null,
        conversationExternalId: (string) \Illuminate\Support\Str::uuid7(),
        messageId: (string) \Illuminate\Support\Str::uuid7(),
        traceId: 'trace-1',
        stage: $overrides['stage'] ?? 'input',
        guardType: $overrides['guardType'] ?? 'pii',
        outcome: $overrides['outcome'] ?? 'redacted',
        severity: $overrides['severity'] ?? null,
        detail: $overrides['detail'] ?? ['types' => ['email']],
        sample: $sample,
    );
}

it('writes one immutable guardrail_events row from the event', function (): void {
    $agent = Agent::factory()->create();

    (new RecordGuardrailEventAction)->execute(guardEvent($agent, 'email a@b.com'));

    $row = GuardrailEvent::query()->firstOrFail();

    expect($row->guard_type->value)->toBe('pii')
        ->and($row->stage->value)->toBe('input')
        ->and($row->outcome->value)->toBe('redacted')
        ->and($row->agent_name)->toBe($agent->name)
        ->and($row->user_name)->toBeNull()
        ->and($row->snippet)->toBe('email a@b.com')
        ->and($row->snippet_sha256)->toBe(hash('sha256', 'email a@b.com'))
        ->and($row->detail)->toBe(['types' => ['email']])
        ->and($row->external_id)->not->toBeNull();
});

it('caps the stored snippet but hashes the full original', function (): void {
    config(['chatvia.guardrails.max_snippet_bytes' => 10]);
    $agent = Agent::factory()->create();
    $long = str_repeat('x', 60);

    (new RecordGuardrailEventAction)->execute(guardEvent($agent, $long));

    $row = GuardrailEvent::query()->firstOrFail();

    expect(strlen((string) $row->snippet))->toBe(10)
        ->and($row->snippet_sha256)->toBe(hash('sha256', $long));
});
