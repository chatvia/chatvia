<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Services\AgentModel;

/**
 * The model an agent answers with: its own, or the default chat model while
 * it has none, so an agent made before any model was added still answers.
 * With the chatters' choice on (admin and agent), a turn answers with the
 * picked model only when the agent offers it.
 */
it('answers with an offered pick, else the agent\'s own model, else the default', function (): void {
    $agent = createAgent(User::factory()->create());
    $default = testLlm('default-model');
    $own = testLlm('own-model');
    $offered = testLlm('offered-model');
    $notOffered = testLlm('not-offered-model');
    config(['chatvia.agent.default_model' => (string) $default->id, 'chatvia.ai.chatter_model_choice' => true]);
    $agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    $agent->setSetting('enabled', true, 'model_choice');
    $agent->setSetting('models', [$offered->id], 'model_choice');
    $agent = $agent->fresh();
    $models = app(AgentModel::class);

    $picked = $models->forTurn($agent, $offered->id);
    $refused = $models->forTurn($agent, $notOffered->id);
    $agent->forceFill(['llm_id' => null])->saveQuietly();
    $fallback = $models->for($agent->fresh());

    expect($picked?->id)->toBe($offered->id)
        ->and($refused?->id)->toBe($own->id)
        ->and($fallback?->id)->toBe($default->id);
});
