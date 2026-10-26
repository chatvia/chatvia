<?php

use Domain\Conversation\Actions\Messages\DeleteMessagePairAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Removing a turn from the visitor's history takes the question and its
 * answer, and deletes the files uploaded with them and the images and audio
 * the answer made.
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
function dmpaConversation(array $history = ['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User, 'a2' => MessageRole::Assistant]): array
{
    $conversation = Conversation::factory()->hostedChat()->create();
    $scope = new ChatScopeData($conversation->agent, $conversation->session->external_id, $conversation->external_id);
    $memory = app(ChatMemory::class)->withScope($scope);
    $ids = [];

    foreach ($history as $name => $role) {
        $ids[$name] = (string) Str::uuid();
        $memory->appendMessage(new MessageData(id: $ids[$name], role: $role, content: "text {$name}", type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
    }

    return [$scope, $conversation, $ids];
}

/** @return list<string> */
function dmpaHistoryIds(ChatScopeData $scope): array
{
    return array_column(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation(), 'id');
}

it('removes the message and the one before it from the history', function (): void {
    [$scope, , $ids] = dmpaConversation();

    app(DeleteMessagePairAction::class)->execute($scope, $ids['a2']);

    expect(dmpaHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('deletes the files uploaded with the removed messages', function (): void {
    [$scope, $conversation, $ids] = dmpaConversation();

    $question = Message::factory()->fromUser()->create(['external_id' => $ids['q2'], 'chat_conversation_id' => $conversation->id, 'content' => 'text q2']);
    $upload = Attachment::factory()->forMessage($question)->stored()->create();

    app(DeleteMessagePairAction::class)->execute($scope, $ids['a2']);

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('deletes the images and audio the removed answer made, and keeps the others', function (): void {
    [$scope, $conversation, $ids] = dmpaConversation();
    $made = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $earlier = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $memory = app(ChatMemory::class)->withScope($scope);
    $memory->saveMessages(array_map(static fn (MessageData $message): MessageData => match ($message->id) {
        $ids['a2'] => $message->withMetadata(['artifacts' => [['kind' => 'image', 'data' => ['id' => $made->external_id]]]]),
        $ids['a1'] => $message->withMetadata(['artifacts' => [['kind' => 'audio', 'data' => ['id' => $earlier->external_id]]]]),
        default => $message,
    }, $memory->cachedMessages()));

    app(DeleteMessagePairAction::class)->execute($scope, $ids['a2']);

    expect(GeneratedMedia::query()->find($made->id))->toBeNull()
        ->and(GeneratedMedia::query()->find($earlier->id))->not->toBeNull();
    Storage::disk(GeneratedMedia::DISK)->assertMissing($made->filepath);
});

it('keeps the text meant for the model on the messages that stay', function (): void {
    [$scope, , $ids] = dmpaConversation();
    $memory = app(ChatMemory::class)->withScope($scope);
    $memory->saveMessages(array_map(static fn (MessageData $message): MessageData => $message->withMessageForLlm("masked {$message->content}"), $memory->cachedMessages()));

    app(DeleteMessagePairAction::class)->execute($scope, $ids['a2']);

    expect(array_column($memory->cachedMessages(), 'message_for_llm'))->toBe(['masked text q1', 'masked text a1']);
});
