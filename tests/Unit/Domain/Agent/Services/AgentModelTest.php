<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Services\AgentModel;

/**
 * The model an agent answers with: its own, or the default chat model while
 * it has none, so an agent made before any model was added still answers.
 */
beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
    $this->default = testLlm('default-model');
    config(['chatvia.agent.default_model' => (string) $this->default->id]);
});

it('answers with the agent\'s own model', function (): void {
    $own = testLlm('own-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();

    expect(app(AgentModel::class)->for($this->agent->fresh())?->id)->toBe($own->id);
});

it('answers with the default model while the agent has none', function (): void {
    $this->agent->forceFill(['llm_id' => null])->saveQuietly();

    expect(app(AgentModel::class)->for($this->agent->fresh())?->id)->toBe($this->default->id);
});
