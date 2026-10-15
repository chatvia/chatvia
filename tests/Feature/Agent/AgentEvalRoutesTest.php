<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;
use Inertia\Testing\AssertableInertia;

/**
 * An agent's Evals page: writing eval cases, starting a run of the active
 * ones, and removing runs. Viewing needs agent.evals.view; everything that
 * writes needs agent.evals.manage. Cases from the knowledge base are in
 * EvalCasesFromKnowledgeTest.
 */
beforeEach(function (): void {
    Bus::fake();

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();
    $this->agent = createAgent($this->owner);
});

/** @param  array<string, mixed>  $attributes */
function evalRoutesCase(Agent $agent, array $attributes = []): EvalCase
{
    return EvalCase::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->workspace_id,
        'name' => 'Opening hours',
        'input' => 'When do you open?',
        'scorer_type' => 'contains',
        'scorer_config' => ['value' => 'nine'],
        'active' => true,
        ...$attributes,
    ]);
}

function evalRoutesRun(Agent $agent): EvalRun
{
    return EvalRun::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->workspace_id,
        'status' => EvalRunStatus::Completed,
        'total_cases' => 1,
        'passed_count' => 1,
        'started_at' => now(),
        'finished_at' => now(),
    ]);
}

it('adds a case to the agent, in its workspace', function (): void {
    $this->actingAs($this->owner)
        ->post(route('agents.evals.cases.store', $this->agent), [
            'name' => 'Refunds',
            'input' => 'Can I get a refund?',
            'scorer_type' => 'contains',
            'scorer_config' => ['value' => '30 days'],
        ])
        ->assertRedirect();

    $case = EvalCase::query()->forAgent($this->agent->id)->sole();

    expect($case->name)->toBe('Refunds')
        ->and($case->workspace_id)->toBe($this->agent->workspace_id)
        ->and($case->scorer_config)->toBe(['value' => '30 days']);
});

it('refuses a case without what its scorer needs', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.evals.cases.store', $this->agent), ['name' => 'Refunds', 'input' => 'Refund?', 'scorer_type' => 'contains'])
        ->assertJsonValidationErrors(['scorer_config.value']);

    expect(EvalCase::query()->forAgent($this->agent->id)->exists())->toBeFalse();
});

it('changes and deletes a case of the agent, and 404s one of another agent', function (): void {
    $case = evalRoutesCase($this->agent);
    $elsewhere = evalRoutesCase(createAgent($this->owner));
    $body = ['name' => 'Renamed', 'input' => 'When do you close?', 'scorer_type' => 'contains', 'scorer_config' => ['value' => 'six'], 'active' => false];

    $this->actingAs($this->owner)->patch(route('agents.evals.cases.update', [$this->agent, $case]), $body)->assertRedirect();
    $this->actingAs($this->owner)->patchJson(route('agents.evals.cases.update', [$this->agent, $elsewhere]), $body)->assertNotFound();

    expect($case->fresh())
        ->name->toBe('Renamed')
        ->active->toBeFalse()
        ->and($elsewhere->fresh()->name)->toBe('Opening hours');

    $this->actingAs($this->owner)->deleteJson(route('agents.evals.cases.destroy', [$this->agent, $elsewhere]))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('agents.evals.cases.destroy', [$this->agent, $case]))->assertRedirect();

    expect(EvalCase::query()->whereKey($case->id)->exists())->toBeFalse()
        ->and(EvalCase::query()->whereKey($elsewhere->id)->exists())->toBeTrue();
});

it('starts a run of the active cases in the queue', function (): void {
    evalRoutesCase($this->agent);
    evalRoutesCase($this->agent, ['name' => 'Second']);
    evalRoutesCase($this->agent, ['name' => 'Switched off', 'active' => false]);

    $this->actingAs($this->owner)
        ->post(route('agents.evals.runs.store', $this->agent))
        ->assertRedirect(route('agents.evals', $this->agent));

    $run = EvalRun::query()->forAgent($this->agent->id)->sole();

    expect($run->status)->toBe(EvalRunStatus::Running)
        ->and($run->total_cases)->toBe(2)
        ->and($run->triggered_by_user_id)->toBe($this->owner->id);
    Bus::assertBatched(fn (PendingBatchFake $batch): bool => $batch->jobs->count() === 2);
});

it('deletes one run, or every run of the agent, and leaves other agents\' runs', function (): void {
    $first = evalRoutesRun($this->agent);
    $second = evalRoutesRun($this->agent);
    $elsewhere = evalRoutesRun(createAgent($this->owner));

    $this->actingAs($this->owner)->deleteJson(route('agents.evals.runs.destroy', [$this->agent, $elsewhere]))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('agents.evals.runs.destroy', [$this->agent, $first]))->assertRedirect(route('agents.evals', $this->agent));

    expect(EvalRun::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(EvalRun::query()->whereKey($second->id)->exists())->toBeTrue();

    $this->actingAs($this->owner)->delete(route('agents.evals.runs.clear', $this->agent))->assertRedirect(route('agents.evals', $this->agent));

    expect(EvalRun::query()->forAgent($this->agent->id)->exists())->toBeFalse()
        ->and(EvalRun::query()->whereKey($elsewhere->id)->exists())->toBeTrue();
});

describe('permissions', function (): void {
    it('needs agent.evals.view to see the page and a run', function (): void {
        $run = evalRoutesRun($this->agent);
        $blocked = teammateWithPermissions($this->owner, ['agent.chat.view']);
        $allowed = teammateWithPermissions($this->owner, ['agent.evals.view']);

        $this->actingAs($blocked)->get(route('agents.evals', $this->agent))->assertForbidden();
        $this->actingAs($blocked)->get(route('agents.evals.runs.show', [$this->agent, $run]))->assertForbidden();

        $this->actingAs($allowed)->get(route('agents.evals', $this->agent))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('agents/Evals/index'));
        $this->actingAs($allowed)->get(route('agents.evals.runs.show', [$this->agent, $run]))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('agents/Evals/Run'));
    });

    it('needs agent.evals.manage to write: viewing is not enough', function (): void {
        $case = evalRoutesCase($this->agent);
        $run = evalRoutesRun($this->agent);
        $viewer = teammateWithPermissions($this->owner, ['agent.evals.view']);
        $body = ['name' => 'X', 'input' => 'Y', 'scorer_type' => 'contains', 'scorer_config' => ['value' => 'z']];

        $this->actingAs($viewer)->postJson(route('agents.evals.cases.store', $this->agent), $body)->assertForbidden();
        $this->actingAs($viewer)->patchJson(route('agents.evals.cases.update', [$this->agent, $case]), $body)->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('agents.evals.cases.destroy', [$this->agent, $case]))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('agents.evals.runs.store', $this->agent))->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('agents.evals.runs.destroy', [$this->agent, $run]))->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('agents.evals.runs.clear', $this->agent))->assertForbidden();

        expect(EvalCase::query()->forAgent($this->agent->id)->pluck('name')->all())->toBe(['Opening hours'])
            ->and(EvalRun::query()->forAgent($this->agent->id)->pluck('id')->all())->toBe([$run->id]);
        Bus::assertNothingBatched();
    });

    it('lets a teammate with agent.evals.manage write cases and runs', function (): void {
        $case = evalRoutesCase($this->agent);
        $run = evalRoutesRun($this->agent);
        $manager = teammateWithPermissions($this->owner, ['agent.evals.manage']);
        $body = ['name' => 'Renamed', 'input' => 'Y', 'scorer_type' => 'contains', 'scorer_config' => ['value' => 'z']];

        $this->actingAs($manager)->post(route('agents.evals.cases.store', $this->agent), $body)->assertRedirect();
        $this->actingAs($manager)->patch(route('agents.evals.cases.update', [$this->agent, $case]), $body)->assertRedirect();
        $this->actingAs($manager)->delete(route('agents.evals.runs.destroy', [$this->agent, $run]))->assertRedirect();
        $this->actingAs($manager)->post(route('agents.evals.runs.store', $this->agent))->assertRedirect();
        $this->actingAs($manager)->delete(route('agents.evals.cases.destroy', [$this->agent, $case]))->assertRedirect();

        expect(EvalCase::query()->forAgent($this->agent->id)->pluck('name')->all())->toBe(['Renamed'])
            ->and(EvalRun::query()->forAgent($this->agent->id)->whereKeyNot($run->id)->count())->toBe(1)
            ->and(EvalRun::query()->whereKey($run->id)->exists())->toBeFalse();
    });
});
