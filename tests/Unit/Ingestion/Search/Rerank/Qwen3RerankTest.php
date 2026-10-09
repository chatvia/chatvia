<?php

use Domain\Knowledge\Embedding\Rerank\Qwen3Reranker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.scalewayai.api_key', 'test-key');
    config()->set('services.scalewayai.endpoint', 'https://api.scaleway.test/project-x/v1/');
});

test('is an enabled reranker', function (): void {
    expect((new Qwen3Reranker)->isEnabled())->toBeTrue();
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

    (new Qwen3Reranker)->rerank('what is the largest ocean?', ['a', 'b']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.scaleway.test/project-x/v1/rerank'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'qwen3-embedding-8b'
        && $request['query'] === 'what is the largest ocean?'
        && $request['documents'] === ['a', 'b']);
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

    $reranked = (new Qwen3Reranker)->rerank('q', ['a', 'b', 'c']);

    expect($reranked)->toBe([
        ['index' => 2, 'relevance_score' => 0.95],
        ['index' => 0, 'relevance_score' => 0.71],
        ['index' => 1, 'relevance_score' => 0.55],
    ]);
});

test('sends the configured rerank model and caps results at three by default', function (): void {
    config()->set('services.scalewayai.rerank_model', 'qwen3-reranker-custom');

    Http::fake(['*rerank' => Http::response(['results' => []], 200)]);

    (new Qwen3Reranker)->rerank('q', ['a', 'b', 'c', 'd']);

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'qwen3-reranker-custom'
        && $request['top_n'] === 3);
});

test('fails with a clear message when the API key is missing', function (): void {
    config()->set('services.scalewayai.api_key');

    expect(fn (): array => (new Qwen3Reranker)->rerank('q', ['a']))
        ->toThrow(RuntimeException::class, 'reranker API key is not configured');
});
