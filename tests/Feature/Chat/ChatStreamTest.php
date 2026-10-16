<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Agent\Notifications\AgentRateLimitNotification;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BufferedSseResponder;
use Tests\Support\ScriptedLlmProvider;

/**
 * The hosted chat's streaming endpoint over HTTP: a valid POST goes through
 * the `chat` middleware stack (agent resolution, the message-limit gate), the
 * StreamRequestData factory and ChatStreamService, and comes out as an SSE
 * stream of the turn's events. The streaming engine's own branches are
 * covered by the unit tests of its sources.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
});

/** POST one hosted-chat turn to the agent, in a new conversation. */
function chatStreamTurn(Agent $agent): TestResponse
{
    return test()->post(
        "/chat/{$agent->external_id}/stream/".Str::uuid(),
        ['message' => 'Hi there'],
        ['Accept' => 'text/event-stream'],
    );
}

it('streams the model\'s answer for an authorized chat turn', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hello', ' there', '!'));
    app()->instance(SseResponder::class, new BufferedSseResponder);

    $response = chatStreamTurn($this->agent)->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = collect(BufferedSseResponder::events($response->streamedContent()));

    expect($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Hello there!')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete');
});

it('rejects a chat-stream request with no message (validation)', function (): void {
    $this->postJson(
        "/chat/{$this->agent->external_id}/stream/".Str::uuid(),
        [],
    )->assertStatus(422)->assertJsonValidationErrors(['message']);
});

it('dispatches a rate-limit event once the agent exceeds its per-minute cap', function (): void {
    Event::fake([AgentRateLimitExceeded::class]);

    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $this->agent->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');

    // The per-agent gate runs before the SSE body streams, so the bare POST
    // reaches it without consuming the stream.
    chatStreamTurn($this->agent)->assertOk();
    chatStreamTurn($this->agent)->assertOk();

    Event::assertDispatched(
        AgentRateLimitExceeded::class,
        fn (AgentRateLimitExceeded $e): bool => $e->agent->is($this->agent) && $e->limitPerMinute === 1,
    );
});

it('emails the owner when a rate-limited agent has alerts enabled', function (): void {
    Notification::fake();

    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $this->agent->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');

    chatStreamTurn($this->agent);
    chatStreamTurn($this->agent);

    Notification::assertSentTo(
        $this->agent->workspace->owner,
        AgentRateLimitNotification::class,
    );
});

it('does not email the owner when rate-limit alerts are disabled', function (): void {
    Notification::fake();
    app()->instance(SseResponder::class, new BufferedSseResponder);

    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    chatStreamTurn($this->agent);
    $refused = collect(BufferedSseResponder::events(chatStreamTurn($this->agent)->streamedContent()));

    // The second turn was over the cap, and still no email went out. (The
    // notification center may still get its own, mail-free notifications.)
    expect($refused->firstWhere('type', 'error')['data']['code'] ?? null)->toBe('rate_limited');
    Notification::assertNotSentTo($this->agent->workspace->owner, AgentRateLimitNotification::class);
});
