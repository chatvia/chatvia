<?php

use Domain\Knowledge\Embedding\Services\OpenRouterEmbeddingModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The OpenRouter embedding models offered in the pickers: the curated ones
 * that fit the stored size first, then OpenRouter's own list without its
 * batch copies; only the curated ones when OpenRouter cannot be reached.
 */
beforeEach(function (): void {
    Cache::flush();
    config(['services.openrouter.api_key' => 'sk-or-test']);
});

it('offers the curated models that fit first, then OpenRouter\'s list without batch copies', function (): void {
    Http::fake(['openrouter.ai/api/v1/embeddings/models' => Http::response(['data' => [
        ['id' => 'openai/text-embedding-3-small', 'name' => 'OpenAI: Text Embedding 3 Small'],
        ['id' => 'baai/bge-m3', 'name' => 'BAAI: bge-m3'],
        ['id' => 'openai/text-embedding-3-small:batch', 'name' => 'OpenAI: Text Embedding 3 Small (batch)'],
    ]])]);

    $options = app(OpenRouterEmbeddingModels::class)->options(768);

    expect(array_keys($options))->toBe([
        'openai/text-embedding-3-small',
        'openai/text-embedding-3-large',
        'qwen/qwen3-embedding-8b',
        'google/gemini-embedding-001',
        'baai/bge-m3',
    ])
        ->and($options['baai/bge-m3'])->toBe('BAAI: bge-m3');
});

it('offers the curated models when OpenRouter cannot be reached', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response('Bad gateway', 502)]);

    expect(array_keys(app(OpenRouterEmbeddingModels::class)->options(1024)))
        ->toBe(array_keys(OpenRouterEmbeddingModels::CURATED));
});
