<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The guards the widget controllers share on the ids the widget sends: the
 * conversation calls refuse a call with no session id instead of writing a
 * blank session, and ids that are not UUIDs read as unknown instead of
 * failing with a 500.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('refuses conversation calls that send no session id instead of writing a blank session', function (string $method): void {
    $sessionsBefore = Session::query()->count();

    $this->withHeaders(EmbeddedWidget::headers($this->agent))
        ->json($method, '/widget/conversations')
        ->assertStatus(400)
        ->assertJson(['error' => 'Missing session']);

    expect(Session::query()->count())->toBe($sessionsBefore);
})->with(['GET', 'POST', 'DELETE']);

it('treats ids that are not UUIDs as unknown instead of failing with a 500', function (string $method, string $uri, array $body, int $status): void {
    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
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
