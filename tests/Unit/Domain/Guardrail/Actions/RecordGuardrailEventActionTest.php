<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\RecordGuardrailEventAction;
use Domain\Guardrail\Models\GuardrailEvent;
use Illuminate\Support\Str;
use Tests\Support\Guardrail\GuardrailTriggers;

/**
 * The recorder is the safety log's single writer. Its contract: persist the
 * denormalized identity + verdict, hash the FULL original sample, and store only
 * a size-capped snippet so the log can never itself become a PII sink.
 */
it('writes one immutable guardrail_events row from the event', function (): void {
    $agent = Agent::factory()->create();

    (new RecordGuardrailEventAction)->execute(GuardrailTriggers::event($agent, sample: 'email a@b.com'));

    $row = GuardrailEvent::query()->firstOrFail();

    expect($row->guard_type->value)->toBe('pii')
        ->and($row->stage->value)->toBe('input')
        ->and($row->outcome->value)->toBe('redacted')
        ->and($row->agent_name)->toBe($agent->name)
        ->and($row->user_name)->toBeNull()
        ->and($row->snippet)->toBe('email a@b.com')
        ->and($row->snippet_sha256)->toBe(hash('sha256', 'email a@b.com'))
        ->and($row->detail)->toBe(['types' => ['email']])
        ->and(Str::isUuid($row->external_id))->toBeTrue();
});

it('caps a multibyte snippet on a character boundary but hashes the full original', function (string $sample): void {
    config(['chatvia.guardrails.max_snippet_bytes' => 9]);
    $agent = Agent::factory()->create();

    (new RecordGuardrailEventAction)->execute(GuardrailTriggers::event($agent, sample: $sample));

    $row = GuardrailEvent::query()->firstOrFail();

    expect(mb_check_encoding((string) $row->snippet, 'UTF-8'))->toBeTrue()
        ->and(strlen((string) $row->snippet))->toBeLessThan(9)
        ->and(Str::startsWith($sample, (string) $row->snippet))->toBeTrue()
        ->and($row->snippet_sha256)->toBe(hash('sha256', $sample));
})->with([
    'emoji' => 'ok 😀😀😀😀 done',
]);
