<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentModel;

/**
 * The model an agent answers with: its own, or the default chat model while
 * it has none, so an agent made before any model was added still answers.
 * With the chatters' choice on (admin and agent), a turn answers with the
 * picked model when the agent offers it.
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

/**
 * Turn the chatters' model choice on for the install and the agent, offering
 * these models besides the agent's own.
 *
 * @param  list<int>  $ids
 */
function amtOffer(Agent $agent, array $ids, bool $admin = true, bool $agentOn = true): void
{
    config(['chatvia.ai.chatter_model_choice' => $admin]);
    $agent->setSetting('enabled', $agentOn, 'model_choice');
    $agent->setSetting('models', $ids, 'model_choice');
}

it('offers the agent\'s own model first, then the ones it picked', function (): void {
    $own = testLlm('own-model');
    $second = testLlm('second-model');
    $third = testLlm('third-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    amtOffer($this->agent, [$third->id, $second->id, $own->id]);

    expect(app(AgentModel::class)->offered($this->agent->fresh())->pluck('id')->all())
        ->toBe([$own->id, $third->id, $second->id]);
});

it('offers nothing while the admin or the agent has the choice off', function (bool $admin, bool $agentOn): void {
    $other = testLlm('other-model');
    amtOffer($this->agent, [$other->id], $admin, $agentOn);

    expect(app(AgentModel::class)->offered($this->agent->fresh()))->toBeEmpty();
})->with([
    'admin off' => [false, true],
    'agent off' => [true, false],
]);

it('drops a model that can no longer answer, and offers nothing with one left', function (): void {
    $other = testLlm('other-model');
    $other->provider->forceFill(['is_enabled' => false])->save();
    amtOffer($this->agent, [$other->id]);

    expect(app(AgentModel::class)->offered($this->agent->fresh()))->toBeEmpty();
});

it('answers a turn with the picked model only when it is offered', function (): void {
    $own = testLlm('own-model');
    $offered = testLlm('offered-model');
    $notOffered = testLlm('not-offered-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    amtOffer($this->agent, [$offered->id]);
    $agent = $this->agent->fresh();

    expect(app(AgentModel::class)->forTurn($agent, $offered->id)?->id)->toBe($offered->id)
        ->and(app(AgentModel::class)->forTurn($agent, $notOffered->id)?->id)->toBe($own->id)
        ->and(app(AgentModel::class)->forTurn($agent, null)?->id)->toBe($own->id);
});
