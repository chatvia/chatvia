<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * GET /widget/conversations lists the visitor session's conversations, and
 * starts one for a session of the agent that has none. A session id belongs
 * to one agent: replayed through another agent's widget, it lists none of
 * that session's conversations. The call needs a session id.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('lists the session conversations without starting another one', function (): void {
    $boot = EmbeddedWidget::boot($this->agent);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $boot['session_id']]))
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $boot['conversation_id']);
});

it('starts a conversation for a session that has none when listing', function (): void {
    $session = Session::factory()->create(['agent_id' => $this->agent->id]);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $session->external_id]))
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.title', Conversation::DEFAULT_TITLE);
});

it('lists nothing and starts nothing for a session of another agent', function (): void {
    $session = Session::factory()->create(['agent_id' => Agent::factory()->create()->id]);
    Conversation::factory()->withTitle('Private thread')->create(['chat_session_id' => $session->id, 'is_visible' => true]);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $session->external_id]))
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertExactJson(['conversations' => []]);

    expect(Conversation::query()->where('chat_session_id', $session->id)->count())->toBe(1);
});
