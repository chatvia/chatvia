<?php

use App\Identity\Workspace\Models\User;
use Domain\Evaluation\Models\EvalRun;

/**
 * Clearing the run history of the agent removes every run of it and no
 * other agent's.
 */
it('deletes every run of the agent and leaves other agents\' runs', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    EvalRun::factory()->forAgent($agent)->completed()->count(2)->create();
    $elsewhere = EvalRun::factory()->forAgent(createAgent($owner))->completed()->create();

    $this->actingAs($owner)->delete(route('agents.evals.runs.clear', $agent))->assertRedirect(route('agents.evals', $agent));

    expect(EvalRun::query()->forAgent($agent->id)->exists())->toBeFalse()
        ->and(EvalRun::query()->whereKey($elsewhere->id)->exists())->toBeTrue();
});
