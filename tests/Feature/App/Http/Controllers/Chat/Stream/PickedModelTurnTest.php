<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Str;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * With Multi-model on (admin and agent), a hosted-chat turn answers with the
 * model the chatter picked, sent as `model_id`. A model the agent does not
 * offer answers with the agent's own. The image and video models picked in
 * the header are kept on the conversation, where the media tools read them.
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

function pmtTurn(Agent $agent, ?int $modelId, array $picks = []): void
{
    test()->post("/chat/{$agent->external_id}/stream/".Str::uuid7(), ['message' => 'Hi', 'model_id' => $modelId, ...$picks], ['Accept' => 'text/event-stream'])
        ->assertOk()
        ->streamedContent();
}

it('answers with the model the chatter picked and keeps the picked image and video models on the conversation', function (): void {
    pmtTurn($this->agent, $this->offered->id, ['image_model' => 'fal-ai/qwen-image', 'video_model' => 'kling-4.0', 'save_user_message_immediately' => true]);

    expect($this->llm->sentModels)->toBe(['offered-model'])
        ->and(Message::query()->where('role', 'assistant')->latest('id')->value('llm_id'))->toBe($this->offered->id)
        ->and(Conversation::query()->where('agent_id', $this->agent->id)->sole()->media_models)->toBe(['image' => 'fal-ai/qwen-image', 'video' => 'kling-4.0']);
});

it('answers with the agent\'s own model for a model it does not offer', function (): void {
    $other = testLlm('other-model');

    pmtTurn($this->agent, $other->id);

    expect($this->llm->sentModels)->toBe(['own-model']);
});
