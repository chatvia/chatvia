<?php

use App\Identity\Workspace\Models\User;
use Domain\Evaluation\Models\EvalCase;

/**
 * Writing an agent's eval cases: a new case lands on the agent and its
 * workspace, a case without what its scorer needs is refused, and a case of
 * another agent of the workspace is a 404.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('adds a case to the agent, in its workspace', function (): void {
    $this->actingAs($this->owner)
        ->post(route('agents.evals.cases.store', $this->agent), [
            'name' => 'Refunds',
            'input' => 'Can I get a refund?',
            'scorer_type' => 'contains',
            'scorer_config' => ['value' => '30 days'],
        ])
        ->assertRedirect();

    expect(EvalCase::query()->forAgent($this->agent->id)->sole())
        ->name->toBe('Refunds')
        ->workspace_id->toBe($this->agent->workspace_id)
        ->scorer_config->toBe(['value' => '30 days']);
});

it('refuses a case without what its scorer needs', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.evals.cases.store', $this->agent), ['name' => 'Refunds', 'input' => 'Refund?', 'scorer_type' => 'contains'])
        ->assertJsonValidationErrors(['scorer_config.value']);

    expect(EvalCase::query()->forAgent($this->agent->id)->exists())->toBeFalse();
});

it('changes a case of the agent, and 404s one of another agent', function (): void {
    $case = EvalCase::factory()->forAgent($this->agent)->create(['name' => 'Opening hours']);
    $elsewhere = EvalCase::factory()->forAgent(createAgent($this->owner))->create(['name' => 'Opening hours']);
    $body = ['name' => 'Renamed', 'input' => 'When do you close?', 'scorer_type' => 'contains', 'scorer_config' => ['value' => 'six'], 'active' => false];

    $this->actingAs($this->owner)->patch(route('agents.evals.cases.update', [$this->agent, $case]), $body)->assertRedirect();
    $this->actingAs($this->owner)->patchJson(route('agents.evals.cases.update', [$this->agent, $elsewhere]), $body)->assertNotFound();

    expect($case->fresh())
        ->name->toBe('Renamed')
        ->is_active->toBeFalse()
        ->and($elsewhere->fresh()->name)->toBe('Opening hours');
});

it('deletes a case of the agent, and 404s one of another agent', function (): void {
    $case = EvalCase::factory()->forAgent($this->agent)->create();
    $elsewhere = EvalCase::factory()->forAgent(createAgent($this->owner))->create();

    $this->actingAs($this->owner)->deleteJson(route('agents.evals.cases.destroy', [$this->agent, $elsewhere]))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('agents.evals.cases.destroy', [$this->agent, $case]))->assertRedirect();

    expect(EvalCase::query()->whereKey($case->id)->exists())->toBeFalse()
        ->and(EvalCase::query()->whereKey($elsewhere->id)->exists())->toBeTrue();
});
