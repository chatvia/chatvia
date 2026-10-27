<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatStoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The composer's paperclip uploads a PDF file or an image, which the model
 * for chat files reads. Other types are refused with what may be attached,
 * and every upload is refused while an admin has chat file uploads off.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
    $this->agent = Agent::factory()->create(['is_public' => true]);
});

function acUpload(Agent $agent, UploadedFile $file, string $conversationId = 'pending'): mixed
{
    return test()->post("/chat/{$agent->external_id}/messages/attachments", ['file' => $file], ['X-CONVERSATION-ID' => $conversationId, 'Accept' => 'application/json']);
}

it('stores an image a chatter attaches', function (): void {
    useChatFiles();

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'))->assertOk();

    $attachment = Attachment::query()->sole();

    expect($attachment->mime_type)->toBe('image/png')
        ->and($attachment->pages)->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('keeps a home-screen upload in the agent\'s pending folder, by kind', function (): void {
    useChatFiles();

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'))->assertOk();

    expect(Attachment::query()->sole()->filepath)->toStartWith(ChatStoragePath::pending($this->agent).'/images/');
});

it('never files an upload in a conversation outside the caller\'s session', function (): void {
    useChatFiles();
    $someoneElses = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'), $someoneElses->external_id)->assertOk();

    $attachment = Attachment::query()->sole();
    expect($attachment->chat_conversation_id)->toBeNull()
        ->and($attachment->filepath)->toStartWith(ChatStoragePath::pending($this->agent).'/');
});

it('refuses a file of a type no chat file may be', function (): void {
    useChatFiles();

    acUpload($this->agent, UploadedFile::fake()->create('notes.txt', 1, 'text/plain'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'Only PDF files and images (PNG, JPEG, WebP or GIF) can be attached.']);

    expect(Attachment::query()->count())->toBe(0);
});

it('refuses every upload while an admin has chat file uploads off', function (): void {
    useChatFiles();
    useChatFiles(false);

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'Chatting with files is not available.']);
});
