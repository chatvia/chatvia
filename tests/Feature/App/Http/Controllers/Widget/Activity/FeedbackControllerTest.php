<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Widget\WidgetHeaders;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/feedback rates one of the agent's replies; a note on a
 * thumbs-down is kept only while the agent asks for one.
 */
it('keeps the thumbs-down, and its note only while the agent asks for one', function (bool $asks, ?string $kept): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('allow_feedback_comment', $asks, 'appearance');
    EmbeddedWidget::allowSite($agent);
    $headers = EmbeddedWidget::chatHeaders($agent);
    $reply = Message::factory()->fromAssistant()->create([
        'chat_conversation_id' => Conversation::query()->firstWhere('external_id', $headers[WidgetHeaders::CONVERSATION_ID])->id,
    ]);

    $this->withHeaders([...$headers, WidgetHeaders::MESSAGE_ID => $reply->external_id])
        ->postJson('/widget/feedback', ['feedback' => false, 'comment' => 'The price is out of date.'])
        ->assertSuccessful();

    expect($reply->fresh()->feedback)->toBe(MessageFeedback::Negative)
        ->and($reply->fresh()->feedback_comment)->toBe($kept);
})->with([
    'asks' => [true, 'The price is out of date.'],
    'asks for none' => [false, null],
]);
