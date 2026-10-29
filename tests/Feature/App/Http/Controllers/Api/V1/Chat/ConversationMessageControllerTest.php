<?php

use App\Http\Responses\SseResponder;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Jobs\GenerateConversationTitleJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Api\ActsAsApiOwner;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * Chatting with an agent through the public API. A message is answered as
 * one JSON response (the default) or as SSE. Both run the same
 * ChatTurnStreamer pipeline, so every per-turn gate answers with its SSE
 * error code, which the JSON answer maps to a status. A repeated request id
 * replays the answer it got, and a conversation opened on another surface
 * never takes a message.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->base = "/api/v1/agents/{$this->agent->external_id}";
    Queue::fake([GenerateConversationTitleJob::class]);
});

/** Opens an API conversation and returns it. */
function cmcConversation(): Conversation
{
    $id = test()->withToken(test()->token)
        ->postJson(test()->base.'/conversations')
        ->assertCreated()
        ->json('data.id');

    return Conversation::query()->where('external_id', $id)->sole();
}

it('answers a message as one JSON response, stores the turn as api messages, and replays it for a repeated request_id', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hello', ' there', '!'));
    $conversation = cmcConversation();
    $send = fn () => $this->withToken($this->token)->postJson("{$this->base}/conversations/{$conversation->external_id}/messages", ['message' => 'Hi', 'request_id' => 'abc-123']);

    $send()
        ->assertOk()
        ->assertJsonPath('data.conversation_id', $conversation->external_id)
        ->assertJsonPath('data.message.role', 'assistant')
        ->assertJsonPath('data.message.content', 'Hello there!')
        ->assertJsonPath('data.done_reason', 'complete')
        ->assertJsonPath('data.error', null);
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Second answer'));
    $send()->assertOk()->assertJsonPath('data.message.content', 'Hello there!');

    $messages = Message::query()->where('chat_conversation_id', $conversation->id)->orderBy('id')->get();
    expect($messages->pluck('source')->unique()->all())->toBe([MessageOrigin::Api])
        ->and($messages->pluck('content')->all())->toBe(['Hi', 'Hello there!']);
});

it('streams the reply as SSE when asked, by the flag or the Accept header', function (string $how): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Stream', 'ed'));
    app()->instance(SseResponder::class, new BufferedSseResponder);
    $uri = "{$this->base}/conversations/".cmcConversation()->external_id.'/messages';

    $response = $how === 'flag'
        ? $this->withToken($this->token)->postJson($uri, ['message' => 'Hi', 'stream' => true])
        : $this->withToken($this->token)->post($uri, ['message' => 'Hi'], ['Accept' => 'text/event-stream', 'Authorization' => 'Bearer '.$this->token]);

    $response->assertOk();
    $events = collect(BufferedSseResponder::events($response->streamedContent()));
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream')
        ->and($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Streamed')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete');
})->with(['flag', 'header']);

it('never takes a message into a conversation that was not opened over the API', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Leaked'));
    $sessionId = app(StartChatSessionAction::class)->execute($this->agent, null);
    $conversation = app(StartConversationAction::class)->execute(new ChatScopeData($this->agent, $sessionId), ConversationSource::Widget);

    $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$conversation->external_id}/messages", ['message' => 'Hi'])
        ->assertNotFound();

    expect(Message::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse();
});

it("refuses a message longer than the install's longest-message setting", function (): void {
    config(['chatvia.guardrails.max_message_chars' => 20]);

    $this->withToken($this->token)
        ->postJson($this->base.'/conversations/'.cmcConversation()->external_id.'/messages', ['message' => Str::repeat('a', 21)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('answers 429 once the workspace used its monthly messages or the agent\'s per-minute cap trips', function (Closure $arrange, string $code, bool $retryable): void {
    $allowedFirst = $arrange->call($this);
    $uri = "{$this->base}/conversations/".cmcConversation()->external_id.'/messages';

    for ($sent = 0; $sent < $allowedFirst; $sent++) {
        $this->withToken($this->token)->postJson($uri, ['message' => 'One'])->assertOk();
    }

    $this->withToken($this->token)
        ->postJson($uri, ['message' => 'Two'])
        ->assertStatus(429)
        ->assertJsonPath('code', $code)
        ->assertJsonPath('retryable', $retryable);
})->with([
    'monthly message limit' => [function (): int {
        MessageLimit::factory()->forWorkspace($this->owner->workspace())->reached()->create();

        return 0;
    }, 'message_limit_reached', false],
    'agent per-minute cap' => [function (): int {
        bindFakeLlmProvider(ScriptedLlmProvider::answering('Hi'));
        $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

        return 1;
    }, 'rate_limited', true],
]);
