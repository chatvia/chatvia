<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Domain\Guardrail\Models\GuardrailEvent;
use Tests\Support\Guardrail\GuardrailTriggers;

/**
 * The queued listener writes each guard trigger to the safety log while the
 * install-wide guardrails switch is on, and nothing while it is off.
 * RecordGuardrailEventActionTest covers what the row holds.
 */
it('records the event while guardrails are on, and nothing while the global switch is off', function (bool $enabled, int $rows): void {
    config(['chatvia.guardrails.enabled' => $enabled]);
    $agent = Agent::factory()->create();

    app(RecordGuardrailEvent::class)->handle(GuardrailTriggers::event($agent, sample: 'email a@b.com'));

    expect(GuardrailEvent::query()->where('agent_id', $agent->id)->count())->toBe($rows);
})->with([
    'switch on' => [true, 1],
    'switch off' => [false, 0],
]);
