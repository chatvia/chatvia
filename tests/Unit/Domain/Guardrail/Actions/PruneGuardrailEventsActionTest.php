<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\PruneGuardrailEventsAction;
use Domain\Guardrail\Models\GuardrailEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The nightly prune of the safety log: events older than the retention window
 * are deleted, in batches until none is left; newer ones stay.
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

it('deletes in batches until every old event is gone', function (): void {
    $agent = Agent::factory()->create();
    pgeEvent($agent, 100);
    pgeEvent($agent, 120);
    pgeEvent($agent, 150);
    $recent = pgeEvent($agent, 10);
    DB::enableQueryLog();

    expect(app(PruneGuardrailEventsAction::class)->execute(90, batchSize: 1))->toBe(3)
        // Three batches of one, then one that finds nothing.
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => Str::startsWith($query['query'], 'delete'))->count())->toBe(4)
        ->and(GuardrailEvent::query()->pluck('id')->all())->toBe([$recent->id]);
});
