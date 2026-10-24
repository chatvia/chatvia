<?php

use App\Identity\Workspace\Models\User;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Support\Facades\Bus;

/**
 * Starting a run of the agent's active cases from the Evals page: the run is
 * stamped with who started it and the page reloads. What the run holds and
 * queues is in RunEvalSuiteActionTest.
 */
it('starts a run of the active cases as the signed-in teammate', function (): void {
    Bus::fake();
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    EvalCase::factory()->forAgent($agent)->create();

    $this->actingAs($owner)
        ->post(route('agents.evals.runs.store', $agent))
        ->assertRedirect(route('agents.evals', $agent));

    expect(EvalRun::query()->forAgent($agent->id)->sole()->triggered_by_user_id)->toBe($owner->id);
});
