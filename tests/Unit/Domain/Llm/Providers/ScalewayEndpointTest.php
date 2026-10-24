<?php

declare(strict_types=1);

use Domain\Knowledge\Embedding\Qwen3;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Every Scaleway Generative APIs call must use a configured address, never a
 * URL baked into the code, so each install can point at its own project: chat
 * uses its provider's address, embeddings `services.scalewayai.endpoint`
 * (transcription: ScalewayTranscriberTest).
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

    Http::assertSent(fn (Request $request): bool => Str::startsWith($request->url(), 'https://api.scaleway.test/project-x/v1/'));
});

it('sends the configured embedding model', function (): void {
    config(['services.scalewayai.embedding_model' => 'embed-model-x']);
    Http::fake(['*/embeddings' => Http::response(['data' => [['embedding' => array_fill(0, 1024, 0.1)]]])]);

    app(Qwen3::class)->embed('hello');

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/embeddings') && $request['model'] === 'embed-model-x');
});
