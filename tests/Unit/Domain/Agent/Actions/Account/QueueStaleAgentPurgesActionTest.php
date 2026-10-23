<?php

use Domain\Agent\Actions\Account\QueueStaleAgentPurgesAction;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Bus;

/**
 * The daily backstop queues the purge again only for agents deleted more than
 * a day ago that are still there: not for live agents, and not for a delete
 * whose own purge may still be running.
 */
it('queues the purge of agents deleted more than a day ago', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $live = Agent::factory()->create();
    $stale = Agent::factory()->create();
    $recent = Agent::factory()->create();

    $stale->delete();
    $stale->forceFill(['deleted_at' => now()->subHours(25)])->saveQuietly();
    $recent->delete();

    $queued = app(QueueStaleAgentPurgesAction::class)->execute();

    expect($queued)->toBe(1);
    Bus::assertDispatchedTimes(PurgeAgentJob::class, 1);
    Bus::assertDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => $job->agentId === $stale->id);
    Bus::assertNotDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => in_array($job->agentId, [$live->id, $recent->id], true));
});
