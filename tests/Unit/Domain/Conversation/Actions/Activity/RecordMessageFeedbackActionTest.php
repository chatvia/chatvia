<?php

use Domain\Analytics\Events\FeedbackGiven;
use Domain\Conversation\Actions\Activity\RecordMessageFeedbackAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * A visitor's rating of a reply is stored once, from the live history when
 * it holds the reply and from the database otherwise, and analytics hears it
 * once. A thumbs-down keeps the visitor's note on what was wrong, unless the
 * agent turned that note off. A reply
 * outside the visitor's own conversation cannot be rated.
 */
beforeEach(function (): void {
    Event::fake([FeedbackGiven::class]);
});

function rmfaScope(): ChatScopeData
{
    $conversation = Conversation::factory()->hostedChat()->create();

    return new ChatScopeData($conversation->agent, $conversation->session->external_id, $conversation->external_id);
}

function rmfaMessage(ChatScopeData $scope, string $externalId): Message
{
    return Message::factory()->fromAssistant()->create([
        'external_id' => $externalId,
        'chat_conversation_id' => Conversation::query()->where('external_id', $scope->conversationId)->value('id'),
        'content' => 'Hello!',
        'feedback' => null,
    ]);
}

it('records the rating once when the message is in the live memory', function (): void {
    $scope = rmfaScope();
    $messageId = (string) Str::uuid7();

    $memory = app(ChatMemory::class)->withScope($scope);
    $memory->appendMessage(new MessageData(
        id: $messageId,
        role: MessageRole::Assistant,
        content: 'Hello!',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        llm_id: 7,
    ));
    rmfaMessage($scope, $messageId);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Positive);

    expect(Message::query()->where('external_id', $messageId)->value('feedback'))->toBe(MessageFeedback::Positive)
        ->and($memory->getMessageById($messageId)?->feedback)->toBe('positive');

    // The live path already wrote the row, so the database path sees the
    // rating unchanged and must not count it a second time.
    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
    Event::assertDispatched(FeedbackGiven::class, fn (FeedbackGiven $event): bool => $event->llmId === 7);
});

it('still records the rating when the live memory is cold', function (): void {
    $scope = rmfaScope();
    $messageId = (string) Str::uuid7();
    rmfaMessage($scope, $messageId);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Negative);

    expect(Message::query()->where('external_id', $messageId)->value('feedback'))->toBe(MessageFeedback::Negative);

    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
    Event::assertDispatched(FeedbackGiven::class, fn (FeedbackGiven $event): bool => $event->feedback === 'negative'
        && $event->messageExternalId === $messageId
        && $event->conversationExternalId === $scope->conversationId
        // Analytics rows are keyed by the agent's workspace.
        && $event->workspaceId === $scope->agent->workspace_id
        // The model lives only in the live memory.
        && $event->llmId === null);
});

it('clears a rating with null', function (): void {
    $scope = rmfaScope();
    $messageId = (string) Str::uuid7();
    rmfaMessage($scope, $messageId)->update(['feedback' => MessageFeedback::Positive]);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, null);

    expect(Message::query()->where('external_id', $messageId)->value('feedback'))->toBeNull();
});

it('keeps a note added to a thumbs-down without counting the rating again', function (): void {
    $scope = rmfaScope();
    $messageId = (string) Str::uuid7();
    rmfaMessage($scope, $messageId);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Negative);
    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Negative, "  The refund period is wrong.\n ");

    expect(Message::query()->where('external_id', $messageId)->value('feedback_comment'))->toBe('The refund period is wrong.');
    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
});

it('drops the note when the rating is not a thumbs-down', function (): void {
    $scope = rmfaScope();
    $messageId = (string) Str::uuid7();
    rmfaMessage($scope, $messageId)->update(['feedback' => MessageFeedback::Negative, 'feedback_comment' => 'Wrong price.']);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Positive, 'Ignored.');

    $message = Message::query()->where('external_id', $messageId)->first();

    expect($message->feedback)->toBe(MessageFeedback::Positive)
        ->and($message->feedback_comment)->toBeNull();
});

it('keeps the thumbs-down but drops the note while the agent asks for none', function (): void {
    $scope = rmfaScope();
    $scope->agent->setSetting('allow_feedback_comment', false, 'appearance');
    $messageId = (string) Str::uuid7();
    rmfaMessage($scope, $messageId);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Negative, 'The refund period is wrong.');

    $message = Message::query()->where('external_id', $messageId)->first();

    expect($message->feedback)->toBe(MessageFeedback::Negative)
        ->and($message->feedback_comment)->toBeNull();
    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
});

it('cannot rate a message of a conversation outside the scope', function (): void {
    // The database path must look the message up inside the visitor's own
    // conversation, never by its id alone.
    $victim = rmfaScope();
    $messageId = (string) Str::uuid7();
    rmfaMessage($victim, $messageId);

    $attacker = rmfaScope();

    app(RecordMessageFeedbackAction::class)->execute(
        $attacker->forConversation($victim->conversationId),
        $messageId,
        MessageFeedback::Negative,
    );

    expect(Message::query()->where('external_id', $messageId)->value('feedback'))->toBeNull();
    Event::assertNotDispatched(FeedbackGiven::class);
});
