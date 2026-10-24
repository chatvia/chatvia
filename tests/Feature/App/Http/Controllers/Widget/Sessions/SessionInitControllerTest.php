<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/sessions/init, the widget's boot call: a new visitor gets a
 * session and a widget conversation, and the next page load resumes both.
 * InitializeWidgetSessionActionTest covers the rules behind it.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('boots a session with a widget conversation and resumes both on the next load', function (): void {
    $boot = EmbeddedWidget::boot($this->agent);

    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->value('source'))
        ->toBe(ConversationSource::Widget);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
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
