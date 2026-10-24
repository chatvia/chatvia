<?php

use App\Http\Responses\SseResponder;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Jobs\GenerateConversationTitleJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Uri;
use Mockery\LegacyMockInterface;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/*
|--------------------------------------------------------------------------
| Public API — chatting with an agent
|--------------------------------------------------------------------------
|
| POST conversations opens a conversation on the `api` surface; POST
| messages answers either as one JSON response (default) or as SSE. Both
| modes run the same ChatStreamService pipeline, so every per-turn gate
| answers with its SSE error code — the JSON mode maps it to a status.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
    $this->base = "/api/v1/agents/{$this->agent->external_id}";

    // The title generator makes a non-streaming model call the scripted
    // provider does not answer; it has its own tests. Message persistence
    // still runs on the sync queue.
    Queue::fake([GenerateConversationTitleJob::class]);
});

/** The one conversation of this test's own agent (the test DB is pre-seeded). */
function agentConversation(Agent $agent): Conversation
{
    return Conversation::query()->where('agent_id', $agent->id)->sole();
}

/** Open an API conversation and return its external id. */
function cmaConversation(array $payload = []): string
{
    return test()->withToken(test()->token)
        ->postJson(test()->base.'/conversations', $payload)
        ->assertCreated()
        ->json('data.id');
}

it('opens a hidden conversation on the api surface with a one-day expiry', function (): void {
    $id = cmaConversation(['title' => 'Support run']);

    $conversation = agentConversation($this->agent);

    expect($conversation->external_id)->toBe($id)
        ->and($conversation->source)->toBe(ConversationSource::Api)
        ->and($conversation->title)->toBe('Support run')
        ->and($conversation->is_visible)->toBeFalse()
        ->and($conversation->expires_at->between(now()->addDay()->subMinute(), now()->addDay()->addMinute()))->toBeTrue()
        ->and($conversation->session->workspace_id)->toBe($this->agent->workspace_id)
        ->and($conversation->session->chatter_id)->toBeNull();
});

it('stamps the session with the named chatter, and rejects another workspace\'s', function (): void {
    $chatter = Chatter::factory()->create(['workspace_id' => $this->agent->workspace_id]);

    cmaConversation(['chatter_id' => $chatter->external_id]);
    expect(agentConversation($this->agent)->session->chatter_id)->toBe($chatter->id);

    $foreign = Chatter::factory()->create();
    $this->withToken($this->token)
        ->postJson($this->base.'/conversations', ['chatter_id' => $foreign->external_id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['chatter_id']);
});

it('answers a message as one JSON response and persists the turn as api messages', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hello', ' there', '!'));
    $id = cmaConversation();

    $response = $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$id}/messages", ['message' => 'Hi']);
    $response
        ->assertOk()
        ->assertJsonPath('data.conversation_id', $id)
        ->assertJsonPath('data.message.role', 'assistant')
        ->assertJsonPath('data.message.content', 'Hello there!')
        ->assertJsonPath('data.done_reason', 'complete')
        ->assertJsonPath('data.error', null);

    $roles = Message::query()->where('chat_conversation_id', agentConversation($this->agent)->id)->orderBy('id')->get();
    expect($roles->pluck('source')->unique()->all())->toBe([MessageOrigin::Api])
        ->and($roles->pluck('content')->all())->toBe(['Hi', 'Hello there!']);
});

it('streams the reply as SSE when asked, via the flag or the Accept header', function (string $how): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Stream', 'ed'));
    app()->instance(SseResponder::class, new BufferedSseResponder);
    $id = cmaConversation();

    $response = $how === 'flag'
        ? $this->withToken($this->token)->postJson("{$this->base}/conversations/{$id}/messages", ['message' => 'Hi', 'stream' => true])
        : $this->withToken($this->token)->post("{$this->base}/conversations/{$id}/messages", ['message' => 'Hi'], ['Accept' => 'text/event-stream', 'Authorization' => 'Bearer '.$this->token]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = collect(BufferedSseResponder::events($response->streamedContent()));
    expect($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Streamed')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete');
})->with(['flag', 'header']);

it('answers 429 with the SSE error code once the workspace limit is reached', function (): void {
    $this->mock(MessageAllowance::class, function (LegacyMockInterface $mock): void {
        $mock->shouldReceive('canSend')->andReturnFalse();
    });
    $id = cmaConversation();

    $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$id}/messages", ['message' => 'Hi'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'message_limit_reached')
        ->assertJsonPath('retryable', false);
});

it('answers 429 rate_limited once the agent\'s own per-minute cap trips', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hi'));
    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $id = cmaConversation();

    $this->withToken($this->token)->postJson("{$this->base}/conversations/{$id}/messages", ['message' => 'One'])->assertOk();
    $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$id}/messages", ['message' => 'Two'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited')
        ->assertJsonPath('retryable', true);
});

it('replays the finished reply for a repeated request_id instead of answering anew', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('First answer'));
    $id = cmaConversation();
    $send = fn () => $this->withToken($this->token)
        ->postJson("{$this->base}/conversations/{$id}/messages", ['message' => 'Hi', 'request_id' => 'abc-123']);

    $send()->assertOk()->assertJsonPath('data.message.content', 'First answer');

    // A different script would answer differently — the replay must not reach it.
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Second answer'));

    $send()->assertOk()->assertJsonPath('data.message.content', 'First answer');
});

it('pages the conversation messages newest first with a cursor', function (): void {
    $id = cmaConversation();
    $conversation = agentConversation($this->agent);
    foreach (['One', 'Two', 'Three'] as $content) {
        Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => $content]);
    }

    $first = $this->withToken($this->token)
        ->getJson("{$this->base}/conversations/{$id}/messages?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.content', 'Three');

    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $this->withToken($this->token)
        ->getJson("{$this->base}/conversations/{$id}/messages?per_page=2&cursor={$cursor}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.content', 'One')
        ->assertJsonPath('next_cursor', null);

    // A cursor the endpoint never handed out starts over at the first page.
    $this->withToken($this->token)
        ->getJson("{$this->base}/conversations/{$id}/messages?per_page=2&cursor=garbage")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('404s the message endpoints for a conversation of another agent', function (): void {
    $id = cmaConversation();
    $other = createAgent($this->owner);

    $this->withToken($this->token)
        ->postJson("/api/v1/agents/{$other->external_id}/conversations/{$id}/messages", ['message' => 'Hi'])
        ->assertNotFound();
});

it('pages the messages by the time they were sent, not by when they were stored', function (): void {
    $conversation = Conversation::query()->where('external_id', cmaConversation())->sole();
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
