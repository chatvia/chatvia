<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * DELETE /widget/conversations clears one conversation from the widget and
 * keeps it for the chat log. It clears nothing of another agent, and a
 * conversation id that is not a UUID is unknown, never "clear them all".
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

it('clears one conversation from the widget but keeps it for the chat log', function (): void {
    $boot = EmbeddedWidget::boot($this->agent);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->deleteJson('/widget/conversations')
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->value('cleared_at'))->not->toBeNull();
});

it('clears nothing for a conversation of another agent or an id that is not a UUID', function (Closure $conversationId): void {
    $boot = EmbeddedWidget::boot($this->agent);
    $other = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($other);
    $foreign = EmbeddedWidget::boot($other);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $conversationId($foreign),
    ]))
        ->deleteJson('/widget/conversations')
        ->assertNotFound()
        ->assertJson(['error' => 'Invalid thread']);

    expect(Conversation::query()->whereNotNull('cleared_at')->exists())->toBeFalse();
})->with([
    'another agent’s conversation' => [fn (array $foreign): string => $foreign['conversation_id']],
    'an id that is not a UUID' => [fn (): string => 'nope'],
]);
