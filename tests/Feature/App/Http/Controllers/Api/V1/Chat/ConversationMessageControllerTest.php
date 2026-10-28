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
use Illuminate\Support\Uri;
use Tests\Support\Api\ActsAsApiOwner;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * Chatting with an agent through the public API. A message is answered as
 * one JSON response (the default) or as SSE. Both run the same
 * ChatTurnStreamer pipeline, so every per-turn gate answers with its SSE
 * error code, which the JSON answer maps to a status. A repeated request id
 * replays the answer it got. The messages list pages by the time a message
 * was sent.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->base = "/api/v1/agents/{$this->agent->external_id}";

    // The title generator makes a non-streaming model call the scripted
    // provider does not answer; it has its own tests. Message persistence
    // still runs on the sync queue.
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

it('answers a message as one JSON response and stores the turn as api messages', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hello', ' there', '!'));
    $conversation = cmcConversation();

    $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$conversation->external_id}/messages", ['message' => 'Hi'])
        ->assertOk()
        ->assertJsonPath('data.conversation_id', $conversation->external_id)
        ->assertJsonPath('data.message.role', 'assistant')
        ->assertJsonPath('data.message.content', 'Hello there!')
        ->assertJsonPath('data.done_reason', 'complete')
        ->assertJsonPath('data.error', null);

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
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = collect(BufferedSseResponder::events($response->streamedContent()));
    expect($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Streamed')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete');
})->with(['flag', 'header']);

it('never takes a message into a conversation that was not opened over the API', function (ConversationSource $source): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Leaked'));
    $sessionId = app(StartChatSessionAction::class)->execute($this->agent, null);
    $conversation = app(StartConversationAction::class)->execute(new ChatScopeData($this->agent, $sessionId), $source);

    $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$conversation->external_id}/messages", ['message' => 'Hi'])
        ->assertNotFound();

    expect(Message::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse();
})->with([ConversationSource::Widget, ConversationSource::HostedChat]);

it("refuses a message longer than the install's longest-message setting", function (): void {
    config(['chatvia.guardrails.max_message_chars' => 20]);

    $this->withToken($this->token)
        ->postJson($this->base.'/conversations/'.cmcConversation()->external_id.'/messages', ['message' => Str::repeat('a', 21)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('answers 429 message_limit_reached once the workspace used its monthly messages', function (): void {
    MessageLimit::factory()->forWorkspace($this->owner->workspace())->reached()->create();
    $conversation = cmcConversation();

    $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$conversation->external_id}/messages", ['message' => 'Hi'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'message_limit_reached')
        ->assertJsonPath('retryable', false);
});

it("answers 429 rate_limited once the agent's own per-minute cap trips", function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hi'));
    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $uri = "{$this->base}/conversations/".cmcConversation()->external_id.'/messages';

    $this->withToken($this->token)->postJson($uri, ['message' => 'One'])->assertOk();
    $this->withToken($this->token)
        ->postJson($uri, ['message' => 'Two'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited')
        ->assertJsonPath('retryable', true);
});

it('replays the finished reply for a repeated request_id instead of answering anew', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('First answer'));
    $uri = "{$this->base}/conversations/".cmcConversation()->external_id.'/messages';
    $send = fn () => $this->withToken($this->token)->postJson($uri, ['message' => 'Hi', 'request_id' => 'abc-123']);

    $send()->assertOk()->assertJsonPath('data.message.content', 'First answer');

    // A different script would answer differently: the replay must not reach it.
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Second answer'));

    $send()->assertOk()->assertJsonPath('data.message.content', 'First answer');
});

it('pages the messages by the time they were sent, not by when they were stored', function (): void {
    $conversation = cmcConversation();
    $at = now()->subHour()->startOfSecond();
    // Stored out of order: a message imported later carries an earlier time.
    $newest = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'created_at' => $at->copy()->addMinutes(2)]);
    $oldest = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'created_at' => $at]);
    $sameSecondEarlier = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'created_at' => $at->copy()->addMinute()]);
    $sameSecondLater = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'created_at' => $at->copy()->addMinute()]);
    $uri = "{$this->base}/conversations/{$conversation->external_id}/messages";

    $first = $this->withToken($this->token)->getJson($uri.'?per_page=2')->assertOk();
    $second = $this->withToken($this->token)->getJson(Uri::of($uri)->withQuery(['per_page' => 2, 'cursor' => $first->json('next_cursor')])->value())->assertOk();

    expect([...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')])
        ->toBe([$newest->external_id, $sameSecondLater->external_id, $sameSecondEarlier->external_id, $oldest->external_id])
        ->and($second->json('next_cursor'))->toBeNull();
});
