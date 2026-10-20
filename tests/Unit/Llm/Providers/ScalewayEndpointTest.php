<?php

use Domain\Knowledge\Embedding\Qwen3;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Operations\Media\ScalewayTranscriber;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Every Scaleway Generative APIs call must use a configured address, never a
 * URL baked into the code, so each install can point at its own project: chat
 * uses its provider's address, embeddings and transcription
 * `services.scalewayai.endpoint`.
 */
beforeEach(function (): void {
    config(['services.scalewayai.endpoint' => 'https://api.scaleway.test/project-x/v1/']);
});

it('builds the chat completion URL from the provider\'s address', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $provider = LlmProvider::factory()->kind(LlmProviderKind::Scaleway)->make(['base_url' => 'https://api.scaleway.test/project-x/v1/']);

    app(ProviderAdapters::class)->for(LlmProviderKind::Scaleway)->client($provider)->completion(['model' => 'mistral-small-3.2-24b-instruct-2506', 'messages' => []]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.test/project-x/v1/chat/completions');
});

it('sends embeddings to the configured endpoint', function (): void {
    Http::fake(['*' => Http::response(['data' => [['embedding' => array_fill(0, 1024, 0.1)]]])]);

    app(Qwen3::class)->embed('hello');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.scaleway.test/project-x/v1/'));
});

it('sends transcriptions to the configured endpoint', function (): void {
    Http::fake(['*' => Http::response(['text' => ' hello '])]);

    $audio = create_temp_file('clip.mp3', 'fake-audio');

    expect(app(ScalewayTranscriber::class)->transcribe($audio))->toBe('hello');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.test/project-x/v1/audio/transcriptions');
});

it('sends the configured embedding and transcription models', function (): void {
    config([
        'services.scalewayai.embedding_model' => 'embed-model-x',
        'services.scalewayai.transcription_model' => 'whisper-x',
    ]);

    Http::fake([
        '*/embeddings' => Http::response(['data' => [['embedding' => array_fill(0, 1024, 0.1)]]]),
        '*/audio/transcriptions' => Http::response(['text' => 'hi']),
    ]);

    app(Qwen3::class)->embed('hello');
    app(ScalewayTranscriber::class)->transcribe(create_temp_file('clip.mp3', 'fake-audio'));

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/embeddings') && $request['model'] === 'embed-model-x');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/audio/transcriptions')
        && collect($request->data())->contains(fn (array $part): bool => ($part['name'] ?? null) === 'model' && ($part['contents'] ?? null) === 'whisper-x'));
});

it('defaults to the generic endpoint, not one Scaleway project\'s', function (): void {
    // Read from the file's text, so an endpoint set in this machine's
    // environment cannot hide the default.
    preg_match("/env\\('SCALEWAYAI_API_ENDPOINT', '([^']*)'\\)/", (string) file_get_contents(config_path('services.php')), $default);

    expect($default[1] ?? null)->toBe('https://api.scaleway.ai/v1');
});

it('takes only MP3 and WAV as they are, and names the upload by its format', function (): void {
    Http::fake(['*' => Http::response(['text' => 'hi'])]);
    $transcriber = app(ScalewayTranscriber::class);

    $transcriber->transcribe(create_temp_file('note.wav', 'fake-audio'));

    expect($transcriber->takesAsIs('wav', 1_000))->toBeTrue()
        ->and($transcriber->takesAsIs('mp3', 25 * 1024 * 1024))->toBeTrue()
        ->and($transcriber->takesAsIs('webm', 1_000))->toBeFalse()
        ->and($transcriber->takesAsIs('mp3', 25 * 1024 * 1024 + 1))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => collect($request->data())
        ->contains(fn (array $part): bool => ($part['name'] ?? null) === 'file' && ($part['filename'] ?? null) === 'audio.wav'));
});
