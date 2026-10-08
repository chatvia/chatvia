<?php

use Domain\Knowledge\Embedding\Qwen3;
use Domain\Llm\Operations\Media\Transcription;
use Domain\Llm\Providers\Scaleway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Every Scaleway Generative APIs call (chat, embeddings, transcription) must
 * use the configured `services.scalewayai.endpoint`, never a URL baked into
 * the code, so each install can point at its own project.
 */
beforeEach(function (): void {
    config([
        'services.scalewayai.api_key' => 'test-key',
        'services.scalewayai.endpoint' => 'https://api.scaleway.test/project-x/v1/',
    ]);
});

it('builds the chat completion URL from the configured endpoint', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    app(Scaleway::class)->completion(['model' => 'mistral-small-3.2-24b-instruct-2506', 'messages' => []]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.test/project-x/v1/chat/completions');
});

it('sends embeddings to the configured endpoint', function (): void {
    Http::fake(['*' => Http::response(['data' => [['embedding' => array_fill(0, 1024, 0.1)]]])]);

    (new Qwen3)->embed('hello');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.scaleway.test/project-x/v1/'));
});

it('sends transcriptions to the configured endpoint', function (): void {
    Http::fake(['*' => Http::response(['text' => ' hello '])]);

    $audio = create_temp_file('clip.mp3', 'fake-audio');

    expect(app(Transcription::class)->transcribe($audio))->toBe('hello');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.test/project-x/v1/audio/transcriptions');
});

it('has no account-specific default endpoint', function (): void {
    $config = require base_path('config/services.php');

    expect($config['scalewayai']['endpoint'] ?? '')->not->toMatch('/[0-9a-f]{8}-[0-9a-f]{4}-/');
})->skip(fn (): bool => getenv('SCALEWAYAI_API_ENDPOINT') !== false || isset($_ENV['SCALEWAYAI_API_ENDPOINT']), 'endpoint set in the environment');
