<?php

use App\Identity\Workspace\Models\User;
use Domain\Evaluation\Models\EvalRun;
use Inertia\Testing\AssertableInertia;

/**
 * One run of the agent: its page, and deleting it from the Evals page. A run
 * of another agent of the workspace is a 404.
 */
it('shows a run of the agent', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $run = EvalRun::factory()->forAgent($agent)->completed()->create();

    $this->actingAs($owner)
        ->get(route('agents.evals.runs.show', [$agent, $run]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard/agents/pages/Evals/Run')
            ->where('run.external_id', $run->external_id));
});

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
