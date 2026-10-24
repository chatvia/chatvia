<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
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

function acUpload(Agent $agent, UploadedFile $file): mixed
{
    return test()->post("/chat/{$agent->external_id}/messages/attachments", ['file' => $file], ['X-CONVERSATION-ID' => 'pending', 'Accept' => 'application/json']);
}

it('stores an image a chatter attaches', function (): void {
    useChatFiles();

    acUpload($this->agent, UploadedFile::fake()->image('receipt.png'))->assertOk();

    $attachment = Attachment::query()->sole();

    expect($attachment->mime_type)->toBe('image/png')
        ->and($attachment->pages)->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
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
