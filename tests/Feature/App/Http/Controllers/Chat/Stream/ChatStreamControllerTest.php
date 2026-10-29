<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Agent\Notifications\AgentRateLimitNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * The hosted chat's streaming endpoint over HTTP: a valid POST goes through
 * the `chat` middleware stack (agent resolution, the message-limit gate), the
 * StreamRequestData factory and ChatTurnStreamer, and comes out as an SSE
 * stream of the turn's events. A message over the admin's length limit and a
 * turn over the agent's per-minute cap are refused. The streaming engine's
 * own branches are covered by the unit tests of its sources.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    app()->instance(SseResponder::class, new BufferedSseResponder);
});

/** POST one hosted-chat turn to the agent, in a new conversation. */
function cscTurn(Agent $agent): TestResponse
{
    return test()->post(
        "/chat/{$agent->external_id}/stream/".Str::uuid7(),
        ['message' => 'Hi there'],
        ['Accept' => 'text/event-stream'],
    );
}

it('streams the model\'s answer for an authorized chat turn', function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Hello', ' there', '!'));

    $response = cscTurn($this->agent)->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = collect(BufferedSseResponder::events($response->streamedContent()));

    expect($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Hello there!')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete');
});

it('refuses a message longer than the admin\'s limit before any model runs', function (): void {
    config(['chatvia.guardrails.max_message_chars' => 300]);

    $this->postJson(
        "/chat/{$this->agent->external_id}/stream/".Str::uuid7(),
        ['message' => Str::repeat('a', 301)],
    )->assertStatus(422)->assertJsonValidationErrors(['message']);
});

it('refuses a turn over the agent\'s per-minute cap and emails the owner who turned alerts on', function (): void {
    Notification::fake();
    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $this->agent->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');

    cscTurn($this->agent);
    $refused = collect(BufferedSseResponder::events(cscTurn($this->agent)->streamedContent()));

    expect($refused->firstWhere('type', 'error')['data']['code'] ?? null)->toBe('rate_limited');
    Notification::assertSentTo($this->agent->workspace->owner, AgentRateLimitNotification::class);
});
