<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\UpdateAgentLlmAction;
use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * UpdateAgentLlmAction sets the agent's single model (the `llm_id` column,
 * by its catalog id) plus its five hyperparameters (stored
 * in the `llm_settings` settings group) — all inside one transaction. The model
 * is resolved with valueOrFail(), so an unknown model id aborts before any
 * write rather than pointing the agent at a non-existent model.
 */
beforeEach(function (): void {
    $owner = User::factory()->create();
    $this->agent = createAgent($owner);
});

it('points the agent at the model with the given id', function (): void {
    $llm = Llm::factory()->create(['name' => 'qwen3-32b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_id: $llm->id,
        temperature: 0.2,
        top_k: 40,
        top_p: 0.9,
        presence_penalty: 0.1,
        frequency_penalty: 0.3,
    ));

    $this->agent->refresh();

    expect($this->agent->llm_id)->toBe($llm->id)
        ->and($this->agent->llm->name)->toBe('qwen3-32b');
});

it('persists every hyperparameter into the llm_settings group', function (): void {
    $llm = Llm::factory()->create(['name' => 'qwen3-32b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_id: $llm->id,
        temperature: 0.2,
        top_k: 40,
        top_p: 0.9,
        presence_penalty: 0.1,
        frequency_penalty: 0.3,
    ));

    // The persisted setting rows are checked (the source of truth the action
    // writes), comparing numerics by value so the test isn't coupled to the
    // settings read-back casting.
    $stored = $this->agent->modelSettings()
        ->where('group', 'llm_settings')
        ->pluck('value', 'key');

    expect((float) $stored['temperature'])->toBe(0.2)
        ->and((int) $stored['top_k'])->toBe(40)
        ->and((float) $stored['top_p'])->toBe(0.9)
        ->and((float) $stored['presence_penalty'])->toBe(0.1)
        ->and((float) $stored['frequency_penalty'])->toBe(0.3);

    // top_k is stored as an integer, so it also reads back typed through the
    // public settings accessor the streaming pipeline uses.
    expect($this->agent->settings('llm_settings')->get('top_k'))->toBe(40);
});

it('overwrites a previously chosen model and its settings', function (): void {
    $small = Llm::factory()->create(['name' => 'qwen3-8b']);
    $bigger = Llm::factory()->create(['name' => 'qwen3-72b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_id: $small->id,
        temperature: 1.0,
        top_k: 0,
        top_p: 1.0,
        presence_penalty: 0.0,
        frequency_penalty: 0.0,
    ));

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_id: $bigger->id,
        temperature: 0.5,
        top_k: 20,
        top_p: 0.8,
        presence_penalty: 0.0,
        frequency_penalty: 0.0,
    ));

    $this->agent->refresh();

    expect($this->agent->llm_id)->toBe($bigger->id);
    // updateOrCreate keyed on (key, group) means no duplicate rows accrue.
    expect($this->agent->modelSettings()->where('group', 'llm_settings')->where('key', 'temperature')->count())->toBe(1)
        ->and((float) $this->agent->modelSettings()->where('group', 'llm_settings')->where('key', 'temperature')->value('value'))->toBe(0.5);
});

it('throws and changes nothing when no model has the given id', function (): void {
    $originalLlmId = $this->agent->llm_id;

    expect(fn () => app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_id: 999_999,
        temperature: 0.2,
        top_k: 40,
        top_p: 0.9,
        presence_penalty: 0.1,
        frequency_penalty: 0.3,
    )))->toThrow(ModelNotFoundException::class);

    $this->agent->refresh();

    // The guard fires before the transaction, so neither the column nor any
    // setting row is touched.
    expect($this->agent->llm_id)->toBe($originalLlmId)
        ->and($this->agent->modelSettings()->where('group', 'llm_settings')->count())->toBe(0);
});

it('stores the reasoning effort in the reasoning group', function (): void {
    $llm = Llm::factory()->create(['name' => 'qwen3-32b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_id: $llm->id,
        temperature: 0.2,
        top_k: 40,
        top_p: 0.9,
        presence_penalty: 0.1,
        frequency_penalty: 0.3,
        reasoning_effort: ReasoningEffort::Medium,
    ));

    expect($this->agent->modelSettings()->where('group', 'reasoning')->value('value'))->toBe('medium');
});
