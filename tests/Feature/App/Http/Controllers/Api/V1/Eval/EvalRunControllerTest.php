<?php

use Domain\Evaluation\Models\EvalCase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's eval runs through the public API, for CI: starting a run of
 * the active cases and polling it by id. A run with no active cases
 * completes at once.
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
