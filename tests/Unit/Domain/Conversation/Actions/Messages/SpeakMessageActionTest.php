<?php

use Domain\Conversation\Actions\Messages\SpeakMessageAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Domain\Llm\Contracts\Speaker;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Llm\FakeSpeaker;

/**
 * A reply is read aloud once and saved, and only a reply of the visitor's
 * own conversation is read: never another conversation's, never a visitor
 * message, never one with nothing to say.
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
