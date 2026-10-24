<?php

declare(strict_types=1);

use Domain\Llm\Operations\Media\ScalewayTranscriber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Scaleway speech to text (Settings → AI → Speech to text): the audio goes to
 * the configured Scaleway address (`services.scalewayai.endpoint`, never one
 * baked into the code) with the configured model.
 */
beforeEach(function (): void {
    config(['services.scalewayai.endpoint' => 'https://api.scaleway.test/project-x/v1/']);
});

it('sends the audio to the configured endpoint and returns the trimmed text', function (): void {
    Http::fake(['*' => Http::response(['text' => ' hello '])]);

    expect(app(ScalewayTranscriber::class)->transcribe(create_temp_file('clip.mp3', 'fake-audio')))->toBe('hello');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.test/project-x/v1/audio/transcriptions');
});

it('sends the configured transcription model', function (): void {
    config(['services.scalewayai.transcription_model' => 'whisper-x']);
    Http::fake(['*/audio/transcriptions' => Http::response(['text' => 'hi'])]);

    app(ScalewayTranscriber::class)->transcribe(create_temp_file('clip.mp3', 'fake-audio'));

    Http::assertSent(fn (Request $request): bool => collect($request->data())
        ->contains(fn (array $part): bool => ($part['name'] ?? null) === 'model' && ($part['contents'] ?? null) === 'whisper-x'));
});

it('names the upload by its format', function (): void {
    Http::fake(['*' => Http::response(['text' => 'hi'])]);

    app(ScalewayTranscriber::class)->transcribe(create_temp_file('note.wav', 'fake-audio'));

    Http::assertSent(fn (Request $request): bool => collect($request->data())
        ->contains(fn (array $part): bool => ($part['name'] ?? null) === 'file' && ($part['filename'] ?? null) === 'audio.wav'));
});

it('takes only MP3 and WAV up to 25 MB as they are', function (string $format, int $bytes, bool $asIs): void {
    expect(app(ScalewayTranscriber::class)->takesAsIs($format, $bytes))->toBe($asIs);
})->with([
    'wav' => ['wav', 1_000, true],
    'mp3 at the limit' => ['mp3', 25 * 1024 * 1024, true],
    'webm' => ['webm', 1_000, false],
    'mp3 over the limit' => ['mp3', 25 * 1024 * 1024 + 1, false],
]);
