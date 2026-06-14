<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\UpdateAgentLlmAction;
use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * UpdateAgentLlmAction sets the agent's single model (the `llm_id` column,
 * resolved from the model's external_name) plus its five hyperparameters (stored
 * in the `llm_settings` settings group) — all inside one transaction. The model
 * is resolved with valueOrFail(), so an unknown external_name aborts before any
 * write rather than pointing the agent at a non-existent model.
 */
beforeEach(function (): void {
    $owner = User::factory()->create();
    $this->agent = createAgent($owner);
});

it('points the agent at the model resolved from its external name', function (): void {
    $llm = Llm::factory()->create(['external_name' => 'qwen3-32b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_external_name: 'qwen3-32b',
        temperature: 0.2,
        top_k: 40,
        top_p: 0.9,
        presence_penalty: 0.1,
        frequency_penalty: 0.3,
    ));

    $this->agent->refresh();

    expect($this->agent->llm_id)->toBe($llm->id)
        ->and($this->agent->llm->external_name)->toBe('qwen3-32b');
});

it('persists every hyperparameter into the llm_settings group', function (): void {
    Llm::factory()->create(['external_name' => 'qwen3-32b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_external_name: 'qwen3-32b',
        temperature: 0.2,
        top_k: 40,
        top_p: 0.9,
        presence_penalty: 0.1,
        frequency_penalty: 0.3,
    ));

    // Assert against the persisted setting rows (the source of truth the action
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
    Llm::factory()->create(['external_name' => 'qwen3-8b']);
    $bigger = Llm::factory()->create(['external_name' => 'qwen3-72b']);

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_external_name: 'qwen3-8b',
        temperature: 1.0,
        top_k: 0,
        top_p: 1.0,
        presence_penalty: 0.0,
        frequency_penalty: 0.0,
    ));

    app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_external_name: 'qwen3-72b',
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

it('throws and changes nothing when the model external name is unknown', function (): void {
    $originalLlmId = $this->agent->llm_id;

    expect(fn () => app(UpdateAgentLlmAction::class)->execute($this->agent, new UpdateAgentLlmData(
        model_external_name: 'model-that-does-not-exist',
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
