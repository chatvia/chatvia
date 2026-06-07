<?php

use Domain\Knowledge\Embedding\Qwen3;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Events\LlmTokensConsumed;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * The provider hooks read the `usage` payload off each non-streaming LLM call and
 * push it into the active TokenUsageContext scope, so the surrounding entry point
 * flushes the right total. Covers completion() (chat/ingestion/analysis ops) and
 * the Qwen3 embeddings + rerank endpoints.
 */
beforeEach(function (): void {
    // Providers build an Authorization header from a (typed string) API key before
    // the (faked) request is made; give it a value.
    config(['services.scalewayai.api_key' => 'test-key']);
    config(['chatvia.ai.provider' => 'scaleway']);
});

it('completion() attributes the provider usage total to the active scope', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => 'hi']]],
        'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30, 'total_tokens' => 150],
    ])]);

    app(TokenUsageContext::class)->track(55, function (): void {
        app(LlmProviderInterface::class)->completion(['model' => 'x', 'messages' => []]);
    }, 'chat');

    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->workspaceId === 55 && $e->tokens === 150,
    );
});

it('completion() falls back to prompt+completion when total_tokens is absent', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => 'hi']]],
        'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 45],
    ])]);

    app(TokenUsageContext::class)->track(55, function (): void {
        app(LlmProviderInterface::class)->completion(['model' => 'x', 'messages' => []]);
    });

    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->tokens === 245,
    );
});

it('records nothing when the completion response carries no usage', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    app(TokenUsageContext::class)->track(55, function (): void {
        app(LlmProviderInterface::class)->completion(['model' => 'x', 'messages' => []]);
    });

    Event::assertNotDispatched(LlmTokensConsumed::class);
});

it('embed() attributes usage.total_tokens to the active scope', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    Http::fake(['*' => Http::response([
        'data' => [['embedding' => [0.1, 0.2, 0.3]]],
        'usage' => ['prompt_tokens' => 11, 'total_tokens' => 11],
    ])]);

    app(TokenUsageContext::class)->track(7, function (): void {
        (new Qwen3)->embed('hello world');
    }, 'ingest');

    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->workspaceId === 7 && $e->tokens === 11,
    );
});

it('rerank() attributes usage.total_tokens to the active scope', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    Http::fake(['*' => Http::response([
        'usage' => ['total_tokens' => 99],
        'results' => [
            ['index' => 1, 'relevance_score' => 0.64],
            ['index' => 0, 'relevance_score' => 0.60],
        ],
    ])]);

    $out = app(TokenUsageContext::class)->track(7, fn (): ?array => (new Qwen3)->rerank('q', ['a', 'b']));

    // The rerank result is still returned intact (usage capture is side-channel).
    expect($out)->toHaveCount(2)
        ->and($out[0])->toMatchArray(['index' => 1, 'relevance_score' => 0.64]);

    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->tokens === 99,
    );
});
