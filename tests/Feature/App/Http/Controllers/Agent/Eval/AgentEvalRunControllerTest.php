<?php

use App\Identity\Workspace\Models\User;
use Domain\Evaluation\Models\EvalRun;

/**
 * Deleting one run of the agent from the Evals page. A run of another agent
 * of the workspace is a 404.
 */
it('deletes one run of the agent and 404s one of another agent', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $run = EvalRun::factory()->forAgent($agent)->completed()->create();
    $kept = EvalRun::factory()->forAgent($agent)->completed()->create();
    $elsewhere = EvalRun::factory()->forAgent(createAgent($owner))->completed()->create();

    $this->actingAs($owner)->deleteJson(route('agents.evals.runs.destroy', [$agent, $elsewhere]))->assertNotFound();
    $this->actingAs($owner)->delete(route('agents.evals.runs.destroy', [$agent, $run]))->assertRedirect(route('agents.evals', $agent));

    expect(EvalRun::query()->whereKey($run->id)->exists())->toBeFalse()
        ->and(EvalRun::query()->whereKey([$kept->id, $elsewhere->id])->count())->toBe(2);
});
