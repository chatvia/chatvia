<?php

use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Testing\TestResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/broadcasting/auth signs the presence channel of the visitor's
 * own conversation with a plain 200 (Pusher's format), and refuses another
 * session's conversation or an id that is not a UUID. BroadcastAuthorizerTest
 * owns the rules.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    $this->boot = EmbeddedWidget::boot($this->agent);
});

function bacSign(string $conversationId): TestResponse
{
    return test()->withHeaders(EmbeddedWidget::headers(test()->agent, [WidgetHeaders::SESSION_ID => test()->boot['session_id']]))
        ->postJson('/widget/broadcasting/auth', ['channel_name' => 'presence-chat.'.$conversationId, 'socket_id' => '1234.5678']);
}

it('signs the presence channel of the visitor’s conversation with a plain 200', function (): void {
    bacSign($this->boot['conversation_id'])
        ->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);
});

it('refuses the presence channel of another session or an id that is not a UUID', function (Closure $conversationId): void {
    bacSign($conversationId->call($this))
        ->assertForbidden()
        ->assertExactJson(['error' => 'Unauthorized']);
})->with([
    'another session’s conversation' => [fn (): string => EmbeddedWidget::boot($this->agent)['conversation_id']],
    'an id that is not a UUID' => [fn (): string => 'nope'],
]);
