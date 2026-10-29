<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Retention\PruneAgentActivityAction;
use Domain\Conversation\Models\AgentActivity;

/**
 * The tool-call audit keeps its rows for the retention window and deletes
 * older ones in batches.
 */
it('deletes audit rows older than the retention window, in batches', function (): void {
    $agent = Agent::factory()->create();
    $old = AgentActivity::factory()->forAgent($agent)->count(3)->sequence(
        ['created_at' => now()->subDays(100)],
        ['created_at' => now()->subDays(200)],
        ['created_at' => now()->subDays(300)],
    )->create();
    $recent = AgentActivity::factory()->forAgent($agent)->create(['created_at' => now()->subDays(10)]);

    expect(app(PruneAgentActivityAction::class)->execute(retentionDays: 90, batchSize: 1))->toBe(3)
        ->and(AgentActivity::query()->whereKey($old->pluck('id'))->exists())->toBeFalse()
        ->and(AgentActivity::query()->whereKey($recent->id)->exists())->toBeTrue();
});
