<?php

use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A generated image, audio file or video opens only through the signed link
 * its row makes, only once it is made, and answers byte-range requests so
 * audio and video can play and seek. Any other type downloads.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
});

it('opens a made image in the browser and sends part of an audio file asked for a range', function (): void {
    $media = GeneratedMedia::factory()->stored('png-bytes')->create();

    $response = $this->get($media->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->streamedContent())->toBe('png-bytes');

    $audio = GeneratedMedia::factory()->audio()->stored('0123456789')->create();

    $range = $this->get($audio->url, ['Range' => 'bytes=2-5']);

    $range->assertStatus(206)
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('Accept-Ranges', 'bytes');

    expect($range->streamedContent())->toBe('2345');
});

it('refuses a link without a valid signature, and a file still being made or failed', function (): void {
    $media = GeneratedMedia::factory()->stored()->create();

    $this->get(route('generated-media.view', ['generatedMedia' => $media, 'extension' => 'png']))->assertForbidden();
    $this->get($media->url.'0')->assertForbidden();
    $this->get(Str::replace('.png?', '.html?', $media->url))->assertForbidden();
    $this->get(GeneratedMedia::factory()->create()->url)->assertNotFound();
    $this->get(GeneratedMedia::factory()->failed()->create()->url)->assertNotFound();
});

it('downloads a file of any other type instead of opening it', function (): void {
    $media = GeneratedMedia::factory()->stored()->create(['mime_type' => 'text/html']);

    expect($this->get($media->url)->headers->get('Content-Disposition'))->toStartWith('attachment');
});
