<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Testing\TestResponse;

/**
 * Setup → Model saves the agent's model (one an admin made available), its
 * generation settings and its reasoning effort. While the admin does not let
 * agents reason, only Off or the effort saved before can be sent.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $this->model = testLlm('other-model');
});

/**
 * Save the agent's model with the form's other fields at their defaults.
 *
 * @param  array<string, mixed>  $overrides
 */
function ualSave(User $owner, Agent $agent, Llm $model, array $overrides = []): TestResponse
{
    return test()->actingAs($owner)->patch(route('agents.llm.update', $agent->external_id), [
        'model_id' => $model->id,
        'temperature' => 1,
        'top_k' => 0,
        'top_p' => 1,
        'presence_penalty' => 0,
        'frequency_penalty' => 0,
        ...$overrides,
    ]);
}

it('saves the model and the temperature', function (): void {
    ualSave($this->owner, $this->agent, $this->model, ['temperature' => 0.4])->assertSessionHasNoErrors();

    $agent = $this->agent->fresh();

    expect($agent->llm_id)->toBe($this->model->id)
        ->and((float) $agent->llmSettings()['temperature'])->toBe(0.4);
});

it('refuses a model that is not available', function (): void {
    $this->model->provider->forceFill(['is_enabled' => false])->save();

    ualSave($this->owner, $this->agent, $this->model)->assertSessionHasErrors('model_id');
});

it('saves the reasoning effort while the admin lets agents reason', function (): void {
    config(['chatvia.ai.reasoning.enabled' => true]);

    ualSave($this->owner, $this->agent, $this->model, ['reasoning_effort' => 'high'])->assertSessionHasNoErrors();

    expect($this->agent->fresh()->settings('reasoning')->get('effort'))->toBe('high');
});

it('refuses a new effort while the admin does not let agents reason', function (): void {
    config(['chatvia.ai.reasoning.enabled' => false]);

    ualSave($this->owner, $this->agent, $this->model, ['reasoning_effort' => 'medium'])
        ->assertSessionHasErrors(['reasoning_effort' => 'Your admin has turned reasoning off.']);

    expect($this->agent->fresh()->settings('reasoning')->get('effort'))->not->toBe('medium');
});

it('still saves the effort saved before, or Off, while the admin does not let agents reason', function (string $effort): void {
    config(['chatvia.ai.reasoning.enabled' => false]);
    // Saved earlier, while reasoning was on.
    $this->agent->setSetting('effort', 'high', 'reasoning');

    ualSave($this->owner, $this->agent, $this->model, ['reasoning_effort' => $effort])->assertSessionHasNoErrors();

    expect($this->agent->fresh()->settings('reasoning')->get('effort'))->toBe($effort);
})->with(['the saved one' => 'high', 'off' => 'off']);
