<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Conversation\Actions\Activity\RecordMessageFeedbackAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Event::fake([FeedbackGiven::class]);
});

function feedbackScope(): ChatScopeData
{
    $agent = Agent::factory()->create();
    $session = ChatSession::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withTitle('Feedback chat')->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);

    return new ChatScopeData($agent, $session->external_id, $conversation->external_id);
}

function feedbackMessage(ChatScopeData $scope, string $externalId): Message
{
    return Message::factory()->fromAssistant()->create([
        'external_id' => $externalId,
        'chat_conversation_id' => Conversation::query()->where('external_id', $scope->conversationId)->value('id'),
        'content' => 'Hello!',
        'feedback' => null,
    ]);
}

it('records the rating once when the message is in the live memory', function (): void {
    $scope = feedbackScope();
    $messageId = (string) Str::uuid();

    $memory = app(ChatMemory::class)->withScope($scope);
    $memory->appendMessage(new MessageData(
        id: $messageId,
        role: MessageRole::Assistant,
        content: 'Hello!',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        llm_id: 7,
    ));
    feedbackMessage($scope, $messageId);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Positive);

    expect(Message::where('external_id', $messageId)->value('feedback'))->toBe('positive')
        ->and($memory->getMessageById($messageId)?->feedback)->toBe('positive');

    // The live path already wrote the row, so the database path sees the
    // rating unchanged and must not count it a second time.
    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
    Event::assertDispatched(FeedbackGiven::class, fn (FeedbackGiven $event): bool => $event->llmId === 7);
});

it('still records the rating when the live memory is cold', function (): void {
    $scope = feedbackScope();
    $messageId = (string) Str::uuid();
    feedbackMessage($scope, $messageId);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, MessageFeedback::Negative);

    expect(Message::where('external_id', $messageId)->value('feedback'))->toBe('negative');

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
    $scope = feedbackScope();
    $messageId = (string) Str::uuid();
    feedbackMessage($scope, $messageId)->update(['feedback' => 'positive']);

    app(RecordMessageFeedbackAction::class)->execute($scope, $messageId, null);

    expect(Message::where('external_id', $messageId)->value('feedback'))->toBeNull();
});

it('cannot rate a message of a conversation outside the scope', function (): void {
    // The database path must look the message up inside the visitor's own
    // conversation, never by its id alone.
    $victim = feedbackScope();
    $messageId = (string) Str::uuid();
    feedbackMessage($victim, $messageId);

    $attacker = feedbackScope();

    app(RecordMessageFeedbackAction::class)->execute(
        $attacker->forConversation($victim->conversationId),
        $messageId,
        MessageFeedback::Negative,
    );

    expect(Message::where('external_id', $messageId)->value('feedback'))->toBeNull();
    Event::assertNotDispatched(FeedbackGiven::class);
});
