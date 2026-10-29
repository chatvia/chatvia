<?php

use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Embedding\Rerank\CompatibleReranker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The common `/rerank` API (OpenRouter, Cohere, self-hosted servers): the
 * request, the results best first whatever order they come in, and a server
 * error thrown so callers fall back.
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

it('throws on a server error, so callers fall back', function (): void {
    Http::fake(['rerank.test/v1/rerank' => Http::response(['error' => 'down'], 503)]);

    expect(fn (): array => new CompatibleReranker('https://rerank.test/v1', 'k', 'm')->rerank('q', ['a']))->toThrow(Exception::class);
});
