<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\DeleteMessagePairAction;
use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Removing a turn from the visitor's history takes the question and its
 * answer, and deletes the files uploaded with them.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/**
 * A hosted-chat conversation whose live history holds the named messages, in
 * order. Message ids are uuids in the database, so each name maps to a new one.
 *
 * @param  array<string, MessageRole>  $history  name => role
 * @return array{0: ChatScopeData, 1: Conversation, 2: array<string, string>}
 */
function messagePairConversation(array $history = ['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User, 'a2' => MessageRole::Assistant]): array
{
    $agent = Agent::factory()->create();
    $session = ChatSession::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);

    $scope = new ChatScopeData($agent, $session->external_id, $conversation->external_id);
    $memory = app(ChatMemory::class)->withScope($scope);
    $ids = [];

    foreach ($history as $name => $role) {
        $ids[$name] = (string) Str::uuid();
        $memory->appendMessage(new MessageData(id: $ids[$name], role: $role, content: "text {$name}", type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
    }

    return [$scope, $conversation, $ids];
}

/** @return list<string> */
function messagePairHistoryIds(ChatScopeData $scope): array
{
    return array_column(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation(), 'id');
}

it('removes the message and the one before it from the history', function (): void {
    [$scope, , $ids] = messagePairConversation();

    app(DeleteMessagePairAction::class)->execute($scope, $ids['a2']);

    expect(messagePairHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('deletes the files uploaded with the removed messages', function (): void {
    [$scope, $conversation, $ids] = messagePairConversation();

    $question = Message::factory()->fromUser()->create(['external_id' => $ids['q2'], 'chat_conversation_id' => $conversation->id, 'content' => 'text q2']);
    Storage::disk(Attachment::DISK)->put('users/x/attachments/doc.pdf', 'pdf');
    $upload = Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'chat_message_id' => $question->id,
        'filepath' => 'users/x/attachments/doc.pdf',
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3,
    ]);

    app(DeleteMessagePairAction::class)->execute($scope, $ids['a2']);

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing('users/x/attachments/doc.pdf');
});

it('hands back the question for a regenerate and drops the last exchange', function (): void {
    [$scope, , $ids] = messagePairConversation();

    $result = app(RegenerateMessageAction::class)->execute($scope, $ids['a2'], $ids['q2']);

    expect($result?->userMessageContent)->toBe('text q2')
        ->and(messagePairHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('drops only the question on a regenerate when its answer never landed', function (): void {
    [$scope, , $ids] = messagePairConversation(['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User]);

    $result = app(RegenerateMessageAction::class)->execute($scope, (string) Str::uuid(), $ids['q2']);

    expect($result?->userMessageContent)->toBe('text q2')
        ->and(messagePairHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('regenerates nothing when the question is not in the history', function (): void {
    [$scope, , $ids] = messagePairConversation();

    expect(app(RegenerateMessageAction::class)->execute($scope, $ids['a2'], (string) Str::uuid()))->toBeNull()
        ->and(messagePairHistoryIds($scope))->toBe(array_values($ids));
});
