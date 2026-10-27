<?php

use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;

/**
 * A generated image, audio file or video opens only through the signed link
 * its row makes, only once it is made, and answers byte-range requests so
 * audio and video can play and seek. A video on remote storage is sent to
 * the storage's own short-lived link.
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

it('plays a made video in the browser and answers ranges', function (): void {
    $media = GeneratedMedia::factory()->video()->stored('0123456789')->create();

    $response = $this->get($media->url, ['Range' => 'bytes=0-3']);

    $response->assertStatus(206)
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Accept-Ranges', 'bytes');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->streamedContent())->toBe('0123');
});

it('sends a video on remote storage to the storage\'s own link', function (): void {
    $media = GeneratedMedia::factory()->video()->ready()->create();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->allows('exists')->andReturnTrue();
    $disk->allows('getAdapter')->andReturn(Mockery::mock(FlysystemAdapter::class));
    $disk->allows('providesTemporaryUrls')->andReturnTrue();
    $disk->expects('temporaryUrl')->with($media->filepath, Mockery::any(), ['ResponseContentType' => 'video/mp4'])->andReturn('https://bucket.storage.test/v.mp4?sig=1');
    Storage::set(GeneratedMedia::DISK, $disk);

    $this->get($media->url)->assertRedirect('https://bucket.storage.test/v.mp4?sig=1');
});
