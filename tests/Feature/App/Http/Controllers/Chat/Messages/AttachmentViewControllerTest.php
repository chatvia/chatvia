<?php

use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * A conversation's file opens only through the signed link the attachment
 * makes. The link works on any host, a missing or changed signature is
 * refused, and only a PDF or an image chatters may attach opens in the
 * browser.
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

it('opens a PDF through its signed link, on any host', function (): void {
    $attachment = avcAttachment();

    expect($attachment->url)->toContain('/media/attachments/'.$attachment->external_id.'.pdf?signature=');

    $response = $this->get($attachment->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->streamedContent())->toBe('%PDF-1.4 brochure');

    $this->get(Uri::of($attachment->url)->withScheme('https')->withHost('chat.example.org')->withPort(null)->value())
        ->assertOk();
});

it('refuses a link without a valid signature', function (): void {
    $attachment = avcAttachment();

    $this->get(route('attachments.view', ['attachment' => $attachment, 'extension' => 'pdf']))->assertForbidden();
    $this->get($attachment->url.'0')->assertForbidden();
    $this->get(Str::replace('.pdf?', '.html?', $attachment->url))->assertForbidden();
});

it('opens only an image chatters may attach in the browser and downloads any other file', function (string $mimeType, string $filename, string $disposition): void {
    $response = $this->get(avcAttachment($mimeType, $filename)->url);

    expect($response->headers->get('Content-Disposition'))->toStartWith($disposition);
})->with([
    'an image' => ['image/png', 'receipt.png', 'inline'],
    'a web page' => ['text/html', 'page.html', 'attachment'],
    'an SVG, which can carry script' => ['image/svg+xml', 'logo.svg', 'attachment'],
]);
