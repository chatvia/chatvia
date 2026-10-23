<?php

use App\Identity\Workspace\Models\Workspace;
use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;

/**
 * A conversation's file opens only through the signed link the attachment
 * makes. The link works on any host, a missing or changed signature is
 * refused, and only a PDF opens in the browser.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

function viewableAttachment(string $mimeType = 'application/pdf', string $filename = 'brochure.pdf'): Attachment
{
    // A pending upload: not in a conversation yet.
    return Attachment::factory()->stored('%PDF-1.4 brochure')->create([
        'chat_conversation_id' => null,
        'workspace_id' => Workspace::factory()->create()->id,
        'filename' => $filename,
        'mime_type' => $mimeType,
        'size_bytes' => 17,
    ]);
}

it('opens a PDF through its signed link', function (): void {
    $response = $this->get(viewableAttachment()->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->streamedContent())->toBe('%PDF-1.4 brochure');
});

it('keeps the link valid on another host', function (): void {
    $url = viewableAttachment()->url;

    $this->get('https://chat.example.org'.parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))
        ->assertOk();
});

it('refuses a link without a valid signature', function (): void {
    $attachment = viewableAttachment();

    $this->get(route('attachments.view', $attachment))->assertForbidden();
    $this->get($attachment->url.'0')->assertForbidden();
});

it('downloads a file that is not a PDF instead of opening it', function (): void {
    $response = $this->get(viewableAttachment('text/html', 'page.html')->url);

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('answers 404 once the file is gone', function (): void {
    $attachment = viewableAttachment();
    Storage::disk(Attachment::DISK)->delete($attachment->filepath);

    $this->get($attachment->url)->assertNotFound();
});
