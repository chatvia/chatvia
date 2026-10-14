<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Mockery\MockInterface;

/**
 * HTTP contract of the embedded widget API for a public agent: the headers it
 * reads, the shapes it answers with, and how it refuses calls that miss an id
 * or name another visitor's data.
 */
beforeEach(function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('sites', ['customer.example'], 'widget');
});

/**
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function widgetHeaders(Agent $agent, array $extra = []): array
{
    return [WidgetHeaders::AGENT_ID => $agent->external_id, 'Origin' => 'https://customer.example', ...$extra];
}

/** @return array{session_id: string, conversation_id: string} */
function bootWidget(Agent $agent): array
{
    return test()->withHeaders(widgetHeaders($agent))
        ->postJson('/widget/sessions/init')
        ->assertSuccessful()
        ->json();
}

it('boots a session with a widget conversation and resumes both on the next load', function (): void {
    $boot = bootWidget($this->agent);

    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->value('source'))
        ->toBe(ConversationSource::Widget);

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/sessions/init')
        ->assertSuccessful()
        ->assertJson([
            'session_id' => $boot['session_id'],
            'conversation_id' => $boot['conversation_id'],
            'is_new_session' => false,
            'is_new_conversation' => false,
        ]);
});

it('lists the session conversations without starting another one', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $boot['conversation_id']);
});

it('starts a conversation for a session that has none when listing', function (): void {
    $session = Session::factory()->create(['agent_id' => $this->agent->id]);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $session->external_id]))
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.title', Conversation::DEFAULT_TITLE);
});

it('creates a new widget conversation on request', function (): void {
    $boot = bootWidget($this->agent);

    $id = $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/conversations')
        ->assertCreated()
        ->json('id');

    expect($id)->not->toBe($boot['conversation_id'])
        ->and(Conversation::query()->where('external_id', $id)->value('source'))->toBe(ConversationSource::Widget);
});

it('refuses conversation calls that send no session id instead of writing a blank session', function (string $method): void {
    $sessionsBefore = Session::query()->count();

    $this->withHeaders(widgetHeaders($this->agent))
        ->json($method, '/widget/conversations')
        ->assertStatus(400)
        ->assertJson(['error' => 'Missing session']);

    expect(Session::query()->count())->toBe($sessionsBefore);
})->with(['GET', 'POST', 'DELETE']);

it('clears one conversation from the widget but keeps it for the chat log', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->deleteJson('/widget/conversations')
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->value('cleared_at'))->not->toBeNull();
});

it('will not clear a conversation of another agent', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    $other->setSetting('sites', ['customer.example'], 'widget');
    $foreign = bootWidget($other);
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $foreign['conversation_id'],
    ]))
        ->deleteJson('/widget/conversations')
        ->assertNotFound()
        ->assertJson(['error' => 'Invalid thread']);

    expect(Conversation::query()->where('external_id', $foreign['conversation_id'])->value('cleared_at'))->toBeNull();
});

it('reports the AI as answering a conversation the visitor cannot see', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->getJson('/widget/handoff/status?conversation_id=01a11e0a-0000-7000-8000-000000000000')
        ->assertOk()
        ->assertExactJson([
            'conversation_id' => '01a11e0a-0000-7000-8000-000000000000',
            'handoff_status' => 'ai',
            'is_human_controlled' => false,
            'admin_name' => null,
        ]);
});

it('reports the handoff status of the visitor conversation', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->getJson('/widget/handoff/status?conversation_id='.$boot['conversation_id'])
        ->assertOk()
        ->assertJson(['conversation_id' => $boot['conversation_id'], 'handoff_status' => 'ai', 'is_human_controlled' => false]);
});

it('needs a session to request a human', function (): void {
    $this->withHeaders(widgetHeaders($this->agent))
        ->postJson('/widget/handoff/request', ['conversation_id' => 'abc'])
        ->assertStatus(400)
        ->assertJson(['error' => 'Missing session']);
});

/** Sends one widget heartbeat for the booted conversation. */
function sendHeartbeat(Agent $agent, array $boot): void
{
    test()->withHeaders(widgetHeaders($agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/heartbeat', ['conversation_id' => $boot['conversation_id']])
        ->assertSuccessful();
}

it('moves the activity time of a visible conversation forward on a heartbeat, leaving updated_at alone', function (): void {
    $boot = bootWidget($this->agent);
    $updatedAt = now()->subHours(2)->startOfSecond();
    Conversation::query()->where('external_id', $boot['conversation_id'])->toBase()
        ->update(['is_visible' => true, 'last_activity_at' => now()->subHour(), 'updated_at' => $updatedAt]);

    sendHeartbeat($this->agent, $boot);

    $conversation = Conversation::query()->where('external_id', $boot['conversation_id'])->firstOrFail();
    expect($conversation->last_activity_at)->toBeGreaterThan(now()->subMinute())
        ->and($conversation->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('does not stamp a hidden conversation on a heartbeat', function (): void {
    $boot = bootWidget($this->agent);

    sendHeartbeat($this->agent, $boot);

    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->value('last_activity_at'))->toBeNull();
});

it('stamps the activity time at most once a minute', function (): void {
    $boot = bootWidget($this->agent);
    $recent = now()->subSeconds(20)->startOfSecond();
    Conversation::query()->where('external_id', $boot['conversation_id'])->toBase()
        ->update(['is_visible' => true, 'last_activity_at' => $recent]);

    sendHeartbeat($this->agent, $boot);

    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->firstOrFail()->last_activity_at->equalTo($recent))->toBeTrue();
});

it('rejects feedback that names no message', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/feedback', ['feedback' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_id');
});

it('answers an auth exchange without a token with 401', function (): void {
    $this->withHeaders(widgetHeaders($this->agent))
        ->postJson('/widget/auth/exchange')
        ->assertUnauthorized()
        ->assertJson(['error' => 'Invalid or expired token']);
});

it('signs the presence channel of the visitor conversation with a plain 200', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/broadcasting/auth', [
            'channel_name' => 'presence-chat.'.$boot['conversation_id'],
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);
});

it('refuses the presence channel of another session', function (): void {
    $boot = bootWidget($this->agent);
    $other = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/broadcasting/auth', [
            'channel_name' => 'presence-chat.'.$other['conversation_id'],
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden()
        ->assertExactJson(['error' => 'Unauthorized']);
});

it('refuses to stream without a conversation id', function (): void {
    $boot = bootWidget($this->agent);

    $this->withHeaders(widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertStatus(400);
});

it('refuses to stream on a session of another agent', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    $other->setSetting('sites', ['customer.example'], 'widget');
    $foreign = bootWidget($other);

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $foreign['session_id'],
        WidgetHeaders::CONVERSATION_ID => $foreign['conversation_id'],
    ]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertForbidden();
});

it('treats ids that are not UUIDs as unknown instead of failing with a 500', function (string $method, string $uri, array $body, int $status): void {
    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => 'not-a-uuid',
        WidgetHeaders::CONVERSATION_ID => 'not-a-uuid',
    ]))
        ->json($method, $uri, $body)
        ->assertStatus($status);
})->with([
    'session init' => ['POST', '/widget/sessions/init', [], 201],
    'conversation list' => ['GET', '/widget/conversations', [], 400],
    'messages' => ['GET', '/widget/conversations/messages', [], 400],
    'handoff status' => ['GET', '/widget/handoff/status', ['conversation_id' => 'nope'], 400],
    'broadcast auth' => ['POST', '/widget/broadcasting/auth', ['channel_name' => 'presence-chat.nope', 'socket_id' => '1.2'], 400],
    'stream' => ['POST', '/widget/stream', ['content' => 'Hi'], 403],
]);

it('treats a conversation id that is not a UUID as unknown for a real session', function (): void {
    $boot = bootWidget($this->agent);
    $headers = widgetHeaders($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]);

    $this->withHeaders($headers)
        ->getJson('/widget/handoff/status?conversation_id=nope')
        ->assertOk()
        ->assertJson(['handoff_status' => 'ai']);

    $this->withHeaders($headers)
        ->postJson('/widget/broadcasting/auth', ['channel_name' => 'presence-chat.nope', 'socket_id' => '1.2'])
        ->assertForbidden();

    $this->withHeaders([...$headers, WidgetHeaders::CONVERSATION_ID => 'nope'])
        ->deleteJson('/widget/conversations')
        ->assertNotFound();

    // The bad id did not fall through to "clear every conversation".
    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->value('cleared_at'))->toBeNull();
});

it('names the conversation at once when the widget asks, after the first reply', function (): void {
    $boot = bootWidget($this->agent);

    test()->mock(GenerateConversationTitleAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')->once()->andReturnUsing(function (ChatScopeData $scope): void {
            Conversation::query()->firstWhere('external_id', $scope->conversationId)->update(['title' => 'Order status']);
        });
    });

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/conversations/title')
        ->assertOk()
        ->assertExactJson(['title' => 'Order status']);
});

it('keeps a title that is there, without another AI call', function (): void {
    $boot = bootWidget($this->agent);
    Conversation::query()->firstWhere('external_id', $boot['conversation_id'])->update(['title' => 'Billing question']);

    test()->mock(GenerateConversationTitleAction::class, fn (MockInterface $mock) => $mock->shouldNotReceive('execute'));

    $this->withHeaders(widgetHeaders($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/conversations/title')
        ->assertOk()
        ->assertExactJson(['title' => 'Billing question']);
});

it('will not name a conversation of another agent or another session', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    $other->setSetting('sites', ['customer.example'], 'widget');
    $foreign = bootWidget($other);
    $sameAgent = bootWidget($this->agent);
    $boot = bootWidget($this->agent);

    test()->mock(GenerateConversationTitleAction::class, fn (MockInterface $mock) => $mock->shouldNotReceive('execute'));

    foreach ([$foreign['conversation_id'], $sameAgent['conversation_id']] as $conversationId) {
        $this->withHeaders(widgetHeaders($this->agent, [
            WidgetHeaders::SESSION_ID => $boot['session_id'],
            WidgetHeaders::CONVERSATION_ID => $conversationId,
        ]))
            ->postJson('/widget/conversations/title')
            ->assertNotFound()
            ->assertJson(['error' => 'Invalid thread']);
    }
});

it('needs a session and a conversation to name one', function (): void {
    $this->withHeaders(widgetHeaders($this->agent))
        ->postJson('/widget/conversations/title')
        ->assertBadRequest();
});
