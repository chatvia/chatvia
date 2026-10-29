<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatStoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The composer's paperclip uploads a PDF file or an image, which the model
 * for chat files reads. An upload waits in the agent's pending folder and is
 * never filed in a conversation outside the caller's session.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
    $this->agent = Agent::factory()->create(['is_public' => true]);
});

function acUpload(Agent $agent, UploadedFile $file, string $conversationId = 'pending'): mixed
{
    return test()->post("/chat/{$agent->external_id}/messages/attachments", ['file' => $file], ['X-CONVERSATION-ID' => $conversationId, 'Accept' => 'application/json']);
}

it('stores an image a chatter attaches in the agent\'s pending folder, by kind', function (): void {
    useChatFiles();

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'))->assertOk();

    $attachment = Attachment::query()->sole();

    expect($attachment->mime_type)->toBe('image/png')
        ->and($attachment->pages)->toBeNull()
        ->and($attachment->filepath)->toStartWith(ChatStoragePath::pending($this->agent).'/images/');
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('never files an upload in a conversation outside the caller\'s session', function (): void {
    useChatFiles();
    $someoneElses = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'), $someoneElses->external_id)->assertOk();

    $attachment = Attachment::query()->sole();
    expect($attachment->chat_conversation_id)->toBeNull()
        ->and($attachment->filepath)->toStartWith(ChatStoragePath::pending($this->agent).'/');
});
