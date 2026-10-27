<?php

declare(strict_types=1);

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Str;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * The image and video model picked in the hosted chat's header travel with
 * each message and are kept on the conversation, where the image and video
 * tools read them (checked against what the agent offers when used).
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    bindFakeLlmProvider(ScriptedLlmProvider::answering('The answer.'));
    app()->instance(SseResponder::class, new BufferedSseResponder);
});

function pmmTurn(Agent $agent, string $conversationId, array $picks): void
{
    test()->post(
        route('chat.stream', ['agentSlug' => $agent->external_id, 'conversationId' => $conversationId]),
        ['message' => 'Draw a fox', 'save_user_message_immediately' => true, ...$picks],
        ['Accept' => 'text/event-stream'],
    )->assertOk()->streamedContent();
}

it('keeps the picked models on the conversation, a new one included', function (): void {
    $conversationId = (string) Str::uuid7();

    pmmTurn($this->agent, $conversationId, ['image_model' => 'fal-ai/qwen-image', 'video_model' => 'kling-4.0']);

    expect(Conversation::query()->where('external_id', $conversationId)->sole()->media_models)
        ->toBe(['image' => 'fal-ai/qwen-image', 'video' => 'kling-4.0']);
});

it('refuses a model id that is too long', function (): void {
    $this->post(
        route('chat.stream', ['agentSlug' => $this->agent->external_id, 'conversationId' => Str::uuid7()->toString()]),
        ['message' => 'Draw a fox', 'image_model' => Str::repeat('a', 201)],
        ['Accept' => 'application/json'],
    )->assertUnprocessable();
});
