<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\PruneGuardrailEventsAction;
use Domain\Guardrail\Models\GuardrailEvent;

/**
 * The nightly prune of the safety log: events older than the retention window
 * are deleted; newer ones stay.
 */
function pgeEvent(Agent $agent, int $daysAgo): GuardrailEvent
{
    return GuardrailEvent::factory()->forAgent($agent)->create(['created_at' => now()->subDays($daysAgo)]);
}

it('deletes guardrail events older than the window', function (): void {
    $agent = Agent::factory()->create();
    pgeEvent($agent, 100);
    $recent = pgeEvent($agent, 10);

    expect(app(PruneGuardrailEventsAction::class)->execute(90))->toBe(1)
        ->and(GuardrailEvent::query()->pluck('id')->all())->toBe([$recent->id]);
});
