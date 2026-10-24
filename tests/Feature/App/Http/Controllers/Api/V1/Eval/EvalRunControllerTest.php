<?php

use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's eval runs through the public API, for CI: starting a run of
 * the active cases and polling it by id, and listing the runs without their
 * per-case results. What a run snapshots and queues is RunEvalSuiteAction's
 * and is tested there.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/evals/runs";
});

it('completes a run with no active cases right away, pollable by id', function (): void {
    $id = $this->withToken($this->token)
        ->postJson($this->uri)
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_cases', 0)
        ->json('data.id');

    $this->withToken($this->token)
        ->getJson("{$this->uri}/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.results', [])
        ->assertJsonPath('data.finished_at', fn (?string $at): bool => $at !== null);
});

it('answers a run over active cases as running', function (): void {
    Bus::fake();
    EvalCase::factory()->forAgent($this->agent)->create();

    $this->withToken($this->token)
        ->postJson($this->uri)
        ->assertCreated()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.total_cases', 1);
});

it('lists the runs without the per-case results and shows them on one run', function (): void {
    $run = EvalRun::factory()->forAgent($this->agent)->completed()->create(['total_cases' => 1]);
    $run->results()->create(['case_id' => null, 'case_name' => 'Refunds', 'scorer_type' => 'contains', 'has_passed' => true, 'score' => 1.0]);

    $this->withToken($this->token)
        ->getJson($this->uri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $run->external_id)
        ->assertJsonPath('data.0.results', null);

    $this->withToken($this->token)
        ->getJson("{$this->uri}/{$run->external_id}")
        ->assertOk()
        ->assertJsonPath('data.results.0.case_name', 'Refunds')
        ->assertJsonPath('data.results.0.pass', true);
});
