<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\WidgetHeaders;
use Illuminate\Testing\TestResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * GET /widget/conversations/messages reads the history of the visitor's own
 * conversation only: another visitor replaying the conversation id reads
 * nothing, and another agent's widget does not find it.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);

    $this->boot = EmbeddedWidget::boot($this->agent);
    app(ChatMemory::class)
        ->withContext($this->agent, $this->boot['session_id'], $this->boot['conversation_id'])
        ->appendMessage(new MessageData(id: 'msg-1', role: MessageRole::User, content: 'Where is my order?', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
});

/** Read the booted conversation's messages as this session, through this agent's widget. */
function cmcRead(Agent $agent, string $sessionId): TestResponse
{
    return test()->withHeaders(EmbeddedWidget::headers($agent, [
        WidgetHeaders::SESSION_ID => $sessionId,
        WidgetHeaders::CONVERSATION_ID => test()->boot['conversation_id'],
    ]))->getJson('/widget/conversations/messages');
}

it('reads the visitor’s own conversation, and nothing for another visitor replaying its id', function (): void {
    cmcRead($this->agent, $this->boot['session_id'])
        ->assertOk()
        ->assertJsonPath('0.content', 'Where is my order?');

    cmcRead($this->agent, EmbeddedWidget::boot($this->agent)['session_id'])
        ->assertOk()
        ->assertExactJson([]);
});

it('finds no conversation through another agent’s widget, even with the visitor’s own ids', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($other);

    cmcRead($other, $this->boot['session_id'])->assertNotFound();
});
