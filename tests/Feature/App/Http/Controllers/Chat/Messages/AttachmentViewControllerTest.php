<?php

use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;

/**
 * A conversation's file opens only through the signed link the attachment
 * makes. The link works on any host, a missing or changed signature is
 * refused, and only a PDF opens in the browser.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/** A stored upload in the composer, not in a conversation yet. */
function avcAttachment(string $mimeType = 'application/pdf', string $filename = 'brochure.pdf'): Attachment
{
    return Attachment::factory()->pending()->stored('%PDF-1.4 brochure')->create([
        'filename' => $filename,
        'mime_type' => $mimeType,
        'size_bytes' => 17,
    ]);
}

it('opens a PDF through its signed link', function (): void {
    $response = $this->get(avcAttachment()->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->streamedContent())->toBe('%PDF-1.4 brochure');
});

it('keeps the link valid on another host', function (): void {
    $url = avcAttachment()->url;

    $this->get(Uri::of($url)->withScheme('https')->withHost('chat.example.org')->withPort(null)->value())
        ->assertOk();
});

it('refuses a link without a valid signature', function (): void {
    $attachment = avcAttachment();

    $this->get(route('attachments.view', $attachment))->assertForbidden();
    $this->get($attachment->url.'0')->assertForbidden();
});

it('downloads a file that is not a PDF instead of opening it', function (): void {
    $response = $this->get(avcAttachment('text/html', 'page.html')->url);

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('answers 404 once the file is gone', function (): void {
    $attachment = avcAttachment();
    Storage::disk(Attachment::DISK)->delete($attachment->filepath);

    $this->get($attachment->url)->assertNotFound();
});
