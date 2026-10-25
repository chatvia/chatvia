<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Str;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/feedback rates one of the agent's replies, so it must name
 * the message; a note on a thumbs-down has a length limit and is kept only
 * while the agent asks for one.
 */
it('rejects feedback that names no message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::chatHeaders($agent))
        ->postJson('/widget/feedback', ['feedback' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_id');
});

it('rejects a note longer than the limit', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders([...EmbeddedWidget::chatHeaders($agent), 'X-MESSAGE-ID' => 'm-1'])
        ->postJson('/widget/feedback', ['feedback' => false, 'comment' => Str::repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment');
});

it('keeps the thumbs-down but drops the note while the agent asks for none', function (bool $asks, ?string $kept): void {
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
