<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Testing\TestResponse;

/**
 * Setup → Model saves the agent's model (one an admin made available) and its
 * generation settings. A model that is not available is refused, and so is a
 * new reasoning effort or Multi-model while the admin does not allow it.
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

it('refuses what the admin has not made available and keeps the agent as it was', function (Closure $arrange, array $overrides, string $field): void {
    $arrange->call($this);

    ualSave($this->owner, $this->agent, $this->model, $overrides)->assertSessionHasErrors($field);

    expect($this->agent->fresh())
        ->llm_id->not->toBe($this->model->id)
        ->and($this->agent->fresh()->settings('reasoning')->get('effort'))->not->toBe('medium')
        ->and($this->agent->fresh()->settings('model_choice')->get('enabled'))->toBeFalse();
})->with([
    'a model that is not available' => [fn () => $this->model->provider->forceFill(['is_enabled' => false])->save(), [], 'model_id'],
    'a new effort while reasoning is off' => [fn () => config(['chatvia.ai.reasoning.enabled' => false]), ['reasoning_effort' => 'medium'], 'reasoning_effort'],
    'Multi-model while not allowed' => [fn () => config(['chatvia.ai.chatter_model_choice' => false]), ['model_choice_enabled' => true], 'model_choice_enabled'],
]);
