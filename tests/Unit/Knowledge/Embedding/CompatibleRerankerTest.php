<?php

use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Embedding\Rerank\CompatibleReranker;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The common `/rerank` API (OpenRouter, Cohere, self-hosted servers): the
 * request, the results best first whatever order they come in, and a clear
 * failure without a key.
 */
it('posts the query and documents and returns the results best first', function (): void {
    Http::fake(['rerank.test/v1/rerank' => Http::response(['results' => [
        ['index' => 0, 'relevance_score' => 0.12],
        ['index' => 2, 'relevance_score' => 0.91],
        ['index' => 1, 'relevance_score' => 0.47],
    ]])]);

    $reranker = new CompatibleReranker('https://rerank.test/v1', 'key-1', 'cohere/rerank-4-pro', extraPayload: ['max_tokens_per_doc' => 1000]);
    $ranked = $reranker->rerank('Where is my parcel?', ['Hours', 'Returns', 'Tracking'], 3);

    expect(array_map(fn (RerankedDocumentData $item): array => [$item->index, $item->relevanceScore], $ranked))->toBe([[2, 0.91], [1, 0.47], [0, 0.12]]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://rerank.test/v1/rerank'
        && $request->hasHeader('Authorization', 'Bearer key-1')
        && $request['model'] === 'cohere/rerank-4-pro'
        && $request['query'] === 'Where is my parcel?'
        && $request['documents'] === ['Hours', 'Returns', 'Tracking']
        && $request['top_n'] === 3
        && $request['max_tokens_per_doc'] === 1000);
});

it('cuts to top_n and sends nothing for no documents', function (): void {
    Http::fake(['rerank.test/v1/rerank' => Http::response(['results' => [
        ['index' => 0, 'relevance_score' => 0.2],
        ['index' => 1, 'relevance_score' => 0.9],
    ]])]);

    $reranker = new CompatibleReranker('https://rerank.test/v1', 'key-1', 'm');

    expect($reranker->rerank('q', ['a', 'b'], 1))->toHaveCount(1)
        ->and($reranker->rerank('q', []))->toBe([]);

    Http::assertSentCount(1);
});

it('refuses to run without a key, unless the server needs none', function (): void {
    Http::fake(['rerank.test/v1/rerank' => Http::response(['results' => []])]);

    expect(fn (): array => new CompatibleReranker('https://rerank.test/v1', null, 'm')->rerank('q', ['a']))
        ->toThrow(RuntimeException::class, 'The reranker has no API key');

    expect(new CompatibleReranker('https://rerank.test/v1', null, 'm', keyRequired: false)->rerank('q', ['a']))->toBe([]);
});

it('throws on a server error, so callers fall back', function (): void {
    Http::fake(['rerank.test/v1/rerank' => Http::response(['error' => 'down'], 503)]);

    expect(fn (): array => new CompatibleReranker('https://rerank.test/v1', 'k', 'm')->rerank('q', ['a']))->toThrow(Exception::class);
});

it('gives each model its thresholds, and unmeasured ones the generic bars', function (): void {
    $generic = RerankModelCatalog::thresholds('someone/new-reranker');

    expect($generic->knowledge)->toBe(0.30)
        ->and($generic->toolRead)->toBe(0.30)
        ->and($generic->toolWrite)->toBe(0.40)
        ->and(RerankModelCatalog::thresholds('rerank-v4.0-pro'))->toEqual(RerankModelCatalog::thresholds('cohere/rerank-4-pro'));
});

it('lists the curated OpenRouter rerankers first, then the live ones', function (): void {
    // A fresh client: TestCase fakes this list as empty for every other test.
    Http::swap(new Factory(app('events')));
    Http::fake(['openrouter.ai/api/v1/models?output_modalities=rerank*' => Http::response(['data' => [
        ['id' => 'cohere/rerank-4-pro', 'name' => 'Cohere: Rerank 4 Pro'],
        ['id' => 'brand/new-reranker', 'name' => 'Brand New Reranker'],
    ]])]);
    cache()->forget('rerank:openrouter-models');

    $options = app(RerankModelCatalog::class)->openRouterOptions();

    expect(array_key_first($options))->toBe('cohere/rerank-4-pro')
        ->and($options['cohere/rerank-4-pro'])->toStartWith('Cohere Rerank 4 Pro')
        ->and(array_key_last($options))->toBe('brand/new-reranker');
});
