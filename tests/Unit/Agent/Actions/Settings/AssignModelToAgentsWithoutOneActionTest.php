<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\AssignModelToAgentsWithoutOneAction;
use Domain\Agent\Models\Agent;

/**
 * AssignModelToAgentsWithoutOneAction gives the model to every agent that
 * has none (made before any model was added), and leaves agents that have
 * one alone.
 */
it('gives the model to agents without one, and only to them', function (): void {
    $owner = User::factory()->create();
    $without = createAgent($owner);
    $without->forceFill(['llm_id' => null])->saveQuietly();
    $other = testLlm('other-model');
    $with = createAgent($owner, ['llm_id' => $other->id]);
    $model = testLlm('default-model');
    // The seeded demo agents may have no model either; they count too.
    $withoutModel = Agent::query()->whereNull('llm_id')->count();

    expect(app(AssignModelToAgentsWithoutOneAction::class)->execute($model))->toBe($withoutModel)
        ->and(Agent::query()->whereNull('llm_id')->exists())->toBeFalse()
        ->and($without->refresh()->llm_id)->toBe($model->id)
        ->and($with->refresh()->llm_id)->toBe($other->id);
});
