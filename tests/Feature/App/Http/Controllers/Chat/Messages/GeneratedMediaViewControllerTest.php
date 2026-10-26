<?php

use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;

/**
 * A generated image or audio file opens only through the signed link its row
 * makes, only once it is made, and answers byte-range requests so audio can
 * play and seek.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
});

it('opens a made image in the browser', function (): void {
    $media = GeneratedMedia::factory()->stored('png-bytes')->create();

    $response = $this->get($media->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->streamedContent())->toBe('png-bytes');
});

it('sends part of an audio file when asked for a range', function (): void {
    $media = GeneratedMedia::factory()->audio()->stored('0123456789')->create();

    $response = $this->get($media->url, ['Range' => 'bytes=2-5']);

    $response->assertStatus(206)
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('Accept-Ranges', 'bytes');

    expect($response->streamedContent())->toBe('2345');
});

it('keeps the link valid on another host', function (): void {
    $url = GeneratedMedia::factory()->stored()->create()->url;

    $this->get(Uri::of($url)->withScheme('https')->withHost('chat.example.org')->withPort(null)->value())
        ->assertOk();
});

it('refuses a link without a valid signature', function (): void {
    $media = GeneratedMedia::factory()->stored()->create();

    $this->get(route('generated-media.view', $media))->assertForbidden();
    $this->get($media->url.'0')->assertForbidden();
});

it('is not found while the file is still being made, or when it failed', function (): void {
    $this->get(GeneratedMedia::factory()->create()->url)->assertNotFound();
    $this->get(GeneratedMedia::factory()->failed()->create()->url)->assertNotFound();
});

it('downloads a file of any other type instead of opening it', function (): void {
    $media = GeneratedMedia::factory()->stored()->create(['mime_type' => 'text/html']);

    expect($this->get($media->url)->headers->get('Content-Disposition'))->toStartWith('attachment');
});
