<?php

use Domain\Conversation\Actions\Messages\SpeakMessageAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Exceptions\SpeechNotConfiguredException;
use Domain\Llm\Operations\Media\NullSpeaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Llm\FakeSpeaker;

/**
 * A reply is read aloud once and saved; it is found in the live history or
 * the chat log, only in the visitor's own conversation.
 */
beforeEach(function (): void {
    Storage::fake(MessageSpeechFiles::DISK);
    $this->speaker = new FakeSpeaker;
    app()->instance(Speaker::class, $this->speaker);
});

function smaScope(): ChatScopeData
{
    $conversation = Conversation::factory()->hostedChat()->create();

    return new ChatScopeData($conversation->agent, $conversation->session->external_id, $conversation->external_id);
}

function smaStoredReply(ChatScopeData $scope, string $content, MessageRole $role = MessageRole::Assistant): Message
{
    return Message::factory()->fromAssistant()->create([
        'chat_conversation_id' => Conversation::query()->where('external_id', $scope->conversationId)->value('id'),
        'content' => $content,
        'role' => $role,
    ]);
}

it('reads a stored reply once and saves it', function (): void {
    $scope = smaScope();
    $reply = smaStoredReply($scope, 'We open at **9**.');

    $first = app(SpeakMessageAction::class)->execute($scope, $reply->external_id);
    $second = app(SpeakMessageAction::class)->execute($scope, $reply->external_id);

    expect(Storage::disk(MessageSpeechFiles::DISK)->get($first->path))->toBe('[alto:We open at 9.]')
        ->and($first->mimeType)->toBe('audio/mpeg')
        ->and($first->path)->toEndWith('.mp3')
        ->and($second->path)->toBe($first->path)
        ->and($this->speaker->spoken)->toHaveCount(1);
});

it('reads a reply that is only in the live history', function (): void {
    $scope = smaScope();
    $id = (string) Str::uuid7();
    app(ChatMemory::class)->withScope($scope)->appendMessage(new MessageData(
        id: $id,
        role: MessageRole::Assistant,
        content: 'Live reply.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
    ));

    $speech = app(SpeakMessageAction::class)->execute($scope, $id);

    expect(Storage::disk(MessageSpeechFiles::DISK)->get($speech->path))->toBe('[alto:Live reply.]');
});

it('finds no reply of another conversation, no visitor message, and nothing to say in code', function (): void {
    $scope = smaScope();
    $other = smaStoredReply(smaScope(), 'Not yours.');
    $question = smaStoredReply($scope, 'My question', MessageRole::User);
    $code = smaStoredReply($scope, "```\necho 1;\n```");

    expect(app(SpeakMessageAction::class)->execute($scope, $other->external_id))->toBeNull()
        ->and(app(SpeakMessageAction::class)->execute($scope, $question->external_id))->toBeNull()
        ->and(app(SpeakMessageAction::class)->execute($scope, $code->external_id))->toBeNull()
        ->and($this->speaker->spoken)->toBe([]);
});

it('fails at once without a text-to-speech service', function (): void {
    app()->instance(Speaker::class, new NullSpeaker);

    app(SpeakMessageAction::class)->execute(smaScope(), (string) Str::uuid7());
})->throws(SpeechNotConfiguredException::class);
