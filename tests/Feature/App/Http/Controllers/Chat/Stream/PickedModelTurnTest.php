<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Str;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * With Multi-model on (admin and agent), a hosted-chat turn answers with the
 * model the chatter picked, sent as `model_id`. A model the agent does not
 * offer, or a pick while Multi-model is off, answers with the agent's own.
 */
beforeEach(function (): void {
    $this->own = testLlm('own-model');
    $this->offered = testLlm('offered-model');
    $this->agent = Agent::factory()->create(['is_public' => true, 'llm_id' => $this->own->id]);
    config(['chatvia.ai.chatter_model_choice' => true]);
    $this->agent->setSetting('enabled', true, 'model_choice');
    $this->agent->setSetting('models', [$this->offered->id], 'model_choice');

    $this->llm = ScriptedLlmProvider::answering('The answer.');
    bindFakeLlmProvider($this->llm);
    app()->instance(SseResponder::class, new BufferedSseResponder);
});

function pmtTurn(Agent $agent, ?int $modelId): void
{
    test()->post("/chat/{$agent->external_id}/stream/".Str::uuid(), ['message' => 'Hi', 'model_id' => $modelId], ['Accept' => 'text/event-stream'])
        ->assertOk()
        ->streamedContent();
}

it('answers with the model the chatter picked', function (): void {
    pmtTurn($this->agent, $this->offered->id);

    expect($this->llm->sentModels)->toBe(['offered-model'])
        ->and(Message::query()->where('role', 'assistant')->latest('id')->value('llm_id'))->toBe($this->offered->id);
});

it('answers with the agent\'s own model for a model it does not offer', function (): void {
    $other = testLlm('other-model');

    pmtTurn($this->agent, $other->id);

    expect($this->llm->sentModels)->toBe(['own-model']);
});

it('answers with the agent\'s own model while the admin does not allow Multi-model', function (): void {
    config(['chatvia.ai.chatter_model_choice' => false]);

    pmtTurn($this->agent, $this->offered->id);

    expect($this->llm->sentModels)->toBe(['own-model']);
});
