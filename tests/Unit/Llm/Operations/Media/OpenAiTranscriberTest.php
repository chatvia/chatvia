<?php

use Domain\Llm\Operations\Media\OpenAiTranscriber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Whisper (Settings → AI → Speech to text): the audio goes to the
 * configured OpenAI address with the configured model.
 */
it('sends the audio to the OpenAI transcription endpoint with the configured model', function (): void {
    config([
        'services.openai.api_key' => 'openai-key',
        'services.openai.api_endpoint' => 'https://api.openai.test/v1/',
        'services.openai.transcription_model' => 'whisper-1',
    ]);
    Http::fake(['*' => Http::response(['text' => ' hello there '])]);

    $text = app(OpenAiTranscriber::class)->transcribe(create_temp_file('clip.mp3', 'fake-audio'));

    expect($text)->toBe('hello there')
        ->and(app(OpenAiTranscriber::class)->name())->toBe('whisper-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.test/v1/audio/transcriptions'
        && $request->hasHeader('Authorization', 'Bearer openai-key'));
});

it('names the upload by its format, so OpenAI reads it as it is', function (): void {
    Http::fake(['*' => Http::response(['text' => 'hi'])]);

    app(OpenAiTranscriber::class)->transcribe(create_temp_file('note.WEBM', 'fake-audio'));

    Http::assertSent(fn (Request $request): bool => collect($request->data())
        ->contains(fn (array $part): bool => ($part['name'] ?? null) === 'file' && ($part['filename'] ?? null) === 'audio.webm'));
});

it('takes the formats OpenAI reads, up to 25 MB, as they are', function (): void {
    $transcriber = app(OpenAiTranscriber::class);

    expect($transcriber->takesAsIs('webm', 1_000_000))->toBeTrue()
        ->and($transcriber->takesAsIs('m4a', 25 * 1024 * 1024))->toBeTrue()
        ->and($transcriber->takesAsIs('mp3', 25 * 1024 * 1024 + 1))->toBeFalse()
        ->and($transcriber->takesAsIs('mov', 1_000))->toBeFalse()
        ->and($transcriber->takesAsIs('mp3', 0))->toBeFalse();
});
