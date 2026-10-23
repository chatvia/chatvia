<?php

use Domain\Knowledge\Embedding\OpenRouter;
use Domain\Knowledge\Embedding\Qwen3;
use Domain\Knowledge\Embedding\Services\OpenRouterEmbeddingModels;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Embeddings through OpenRouter, with the key of its chat models: what each
 * request asks for (the model, the stored size), the query instruction of
 * Qwen3 models, and the models offered in the pickers (the curated ones that
 * fit the stored size, then OpenRouter's list without its batch copies).
 */
beforeEach(function (): void {
    Cache::flush();
    config([
        'chatvia.embeddings.dimensions' => 1024,
        'services.openrouter.api_key' => 'sk-or-test',
        'services.openrouter.embedding_model' => 'openai/text-embedding-3-small',
    ]);
});

it('asks OpenRouter for the stored size with its key, and keeps the input order', function (): void {
    Http::fake(['openrouter.ai/api/v1/embeddings' => Http::response(['data' => [
        ['index' => 1, 'embedding' => [0.2, 0.4]],
        ['index' => 0, 'embedding' => [0.1, 0.3]],
    ]])]);

    expect(app(OpenRouter::class)->embedBatch(['first', 'second']))->toBe([[0.1, 0.3], [0.2, 0.4]]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/embeddings'
        && $request->header('Authorization') === ['Bearer sk-or-test']
        && $request['model'] === 'openai/text-embedding-3-small'
        && $request['input'] === ['first', 'second']
        && $request['dimensions'] === 1024);
});

it('keeps the order of vectors that come without an index', function (): void {
    Http::fake(['openrouter.ai/api/v1/embeddings' => Http::response(['data' => [
        ['embedding' => [0.1]],
        ['embedding' => [0.2]],
    ]])]);

    expect(app(OpenRouter::class)->embedBatch(['first', 'second']))->toBe([[0.1], [0.2]]);
});

it('gives questions the Qwen3 instruction only with a Qwen3 model', function (string $model, bool $instructed): void {
    config(['services.openrouter.embedding_model' => $model]);
    Http::fake(['openrouter.ai/api/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1]]]])]);

    app(OpenRouter::class)->embedQuery('Where is my order?');

    Http::assertSent(fn (Request $request): bool => $request['input'] === [$instructed
        ? 'Instruct: '.Qwen3::QUERY_INSTRUCTION."\nQuery: Where is my order?"
        : 'Where is my order?']);
})->with([
    'qwen3' => ['qwen/qwen3-embedding-8b', true],
    'openai' => ['openai/text-embedding-3-small', false],
]);

it('knows the sizes of the curated models, and leaves others to the test embedding', function (string $model, int $stored, bool $fits): void {
    config(['services.openrouter.embedding_model' => $model, 'chatvia.embeddings.dimensions' => $stored]);

    expect(app(OpenRouter::class)->fitsStorage())->toBe($fits);
})->with([
    'openai small up to 1536' => ['openai/text-embedding-3-small', 1536, true],
    'openai small not 2048' => ['openai/text-embedding-3-small', 2048, false],
    'openai large 3072' => ['openai/text-embedding-3-large', 3072, true],
    'mistral only 1024' => ['mistralai/mistral-embed-2312', 768, false],
    'a model not curated' => ['baai/bge-m3', 2048, true],
]);

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
        ->and($options['openai/text-embedding-3-small'])->toBe('OpenAI text-embedding-3-small — Good and cheap. The default.')
        ->and($options['baai/bge-m3'])->toBe('BAAI: bge-m3');
});

it('offers the curated models when OpenRouter cannot be reached', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response('Bad gateway', 502)]);

    expect(array_keys(app(OpenRouterEmbeddingModels::class)->options(1024)))
        ->toBe(array_keys(OpenRouterEmbeddingModels::CURATED));
});
