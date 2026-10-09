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
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Storage::fake(Attachment::DISK);
});

/** Message ids are uuids in the database, so the test names map onto them. */
function pairId(string $name): string
{
    static $ids = [];

    return $ids[$name] ??= (string) Str::uuid();
}

/**
 * @return array{0: ChatScopeData, 1: Conversation}
 */
function messagePairConversation(): array
{
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
    ]);

    $scope = new ChatScopeData($agent, $session->external_id, $conversation->external_id);
    $memory = app(ChatMemory::class)->withScope($scope);

    foreach ([['q1', MessageRole::User], ['a1', MessageRole::Assistant], ['q2', MessageRole::User], ['a2', MessageRole::Assistant]] as [$id, $role]) {
        $memory->appendMessage(new MessageData(id: pairId($id), role: $role, content: "text {$id}", type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
    }

    return [$scope, $conversation];
}

it('removes the message and the one before it from the history', function (): void {
    [$scope] = messagePairConversation();

    app(DeleteMessagePairAction::class)->execute($scope, pairId('a2'));

    expect(array_column(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation(), 'id'))->toBe([pairId('q1'), pairId('a1')]);
});

it('deletes the files uploaded with the removed messages', function (): void {
    [$scope, $conversation] = messagePairConversation();

    $question = Message::create(['external_id' => pairId('q2'), 'chat_conversation_id' => $conversation->id, 'role' => MessageRole::User, 'content' => 'text q2']);
    Storage::disk(Attachment::DISK)->put('users/x/attachments/doc.pdf', 'pdf');
    $upload = Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'chat_message_id' => $question->id,
        'filepath' => 'users/x/attachments/doc.pdf',
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3,
    ]);

    app(DeleteMessagePairAction::class)->execute($scope, pairId('a2'));

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing('users/x/attachments/doc.pdf');
});

it('hands back the question for a regenerate and drops the last exchange', function (): void {
    [$scope] = messagePairConversation();

    $result = app(RegenerateMessageAction::class)->execute($scope, pairId('a2'), pairId('q2'));

    expect($result?->userMessageContent)->toBe('text q2')
        ->and(array_column(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation(), 'id'))->toBe([pairId('q1'), pairId('a1')]);
});

it('regenerates nothing when the question is not in the history', function (): void {
    [$scope] = messagePairConversation();

    expect(app(RegenerateMessageAction::class)->execute($scope, pairId('a2'), (string) Str::uuid()))->toBeNull();
});
