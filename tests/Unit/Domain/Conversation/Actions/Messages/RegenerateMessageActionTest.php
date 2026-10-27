<?php

use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A regenerate takes the last exchange out of the visitor's history and hands
 * back its question to ask again: with its answer, or alone when the answer
 * never landed (its uploads go too). A question not in the history
 * regenerates nothing.
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
function rmaConversation(array $history = ['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User, 'a2' => MessageRole::Assistant]): array
{
    $conversation = Conversation::factory()->hostedChat()->create();
    $scope = new ChatScopeData($conversation->agent, $conversation->session->external_id, $conversation->external_id);
    $memory = app(ChatMemory::class)->withScope($scope);
    $ids = [];

    foreach ($history as $name => $role) {
        $ids[$name] = (string) Str::uuid7();
        $memory->appendMessage(new MessageData(id: $ids[$name], role: $role, content: "text {$name}", type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
    }

    return [$scope, $conversation, $ids];
}

/** @return list<string> */
function rmaHistoryIds(ChatScopeData $scope): array
{
    return array_column(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation(), 'id');
}

it('hands back the question for a regenerate and drops the last exchange', function (): void {
    [$scope, , $ids] = rmaConversation();

    $result = app(RegenerateMessageAction::class)->execute($scope, $ids['a2'], $ids['q2']);

    expect($result?->user_message_content)->toBe('text q2')
        ->and(rmaHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('drops only the question on a regenerate when its answer never landed', function (): void {
    [$scope, , $ids] = rmaConversation(['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User]);

    $result = app(RegenerateMessageAction::class)->execute($scope, (string) Str::uuid7(), $ids['q2']);

    expect($result?->user_message_content)->toBe('text q2')
        ->and(rmaHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('drops only the question when the client has no answer id for it', function (): void {
    [$scope, , $ids] = rmaConversation(['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User]);

    $result = app(RegenerateMessageAction::class)->execute($scope, null, $ids['q2']);

    expect($result?->user_message_content)->toBe('text q2')
        ->and(rmaHistoryIds($scope))->toBe([$ids['q1'], $ids['a1']]);
});

it('deletes the question\'s uploads on a regenerate when its answer never landed', function (): void {
    [$scope, $conversation, $ids] = rmaConversation(['q1' => MessageRole::User, 'a1' => MessageRole::Assistant, 'q2' => MessageRole::User]);
    $question = Message::factory()->fromUser()->create(['external_id' => $ids['q2'], 'chat_conversation_id' => $conversation->id, 'content' => 'text q2']);
    $upload = Attachment::factory()->forMessage($question)->stored()->create();

    app(RegenerateMessageAction::class)->execute($scope, (string) Str::uuid7(), $ids['q2']);

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('regenerates nothing when the question is not in the history', function (): void {
    [$scope, , $ids] = rmaConversation();

    expect(app(RegenerateMessageAction::class)->execute($scope, $ids['a2'], (string) Str::uuid7()))->toBeNull()
        ->and(rmaHistoryIds($scope))->toBe(array_values($ids));
});
