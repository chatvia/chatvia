<?php

use App\Embeddings\Qwen3;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.scalewayai.api_key', 'test-key');
});

test('supports reranking', function (): void {
    expect((new Qwen3)->supportsReranking())->toBeTrue();
});

test('posts the expected envelope to the Scaleway rerank endpoint', function (): void {
    Http::fake([
        '*rerank' => Http::response([
            'id' => 'rerank-1',
            'model' => 'qwen3-embedding-8b',
            'usage' => ['total_tokens' => 12],
            'results' => [
                ['index' => 1, 'document' => ['text' => 'b'], 'relevance_score' => 0.9],
                ['index' => 0, 'document' => ['text' => 'a'], 'relevance_score' => 0.8],
            ],
        ], 200),
    ]);

    (new Qwen3)->rerank('what is the largest ocean?', ['a', 'b']);

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1/rerank'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'qwen3-embedding-8b'
            && $request['query'] === 'what is the largest ocean?'
            && $request['documents'] === ['a', 'b'];
    });
});

test('maps Scaleway results to {index, relevance_score} pairs preserving order', function (): void {
    Http::fake([
        '*rerank' => Http::response([
            'results' => [
                ['index' => 2, 'document' => ['text' => 'c'], 'relevance_score' => 0.95],
                ['index' => 0, 'document' => ['text' => 'a'], 'relevance_score' => 0.71],
                ['index' => 1, 'document' => ['text' => 'b'], 'relevance_score' => 0.55],
            ],
        ], 200),
    ]);

    $reranked = (new Qwen3)->rerank('q', ['a', 'b', 'c']);

    expect($reranked)->toBe([
        ['index' => 2, 'relevance_score' => 0.95],
        ['index' => 0, 'relevance_score' => 0.71],
        ['index' => 1, 'relevance_score' => 0.55],
    ]);
});
