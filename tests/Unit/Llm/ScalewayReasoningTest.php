<?php

use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;

/**
 * Scaleway's Qwen3 models think by default, which adds several seconds before
 * the first answer token. This pipeline is RAG, so reasoning is defaulted OFF
 * (`reasoning_effort=none`) for qwen models only — unless the caller asks for
 * it. Other Scaleway models (analytics / guardrail instruct models) 400 on an
 * unknown `reasoning_effort` field, so they must never receive it. completion()
 * goes through the fakeable Http client, so it exercises the shared
 * disableScalewayReasoning() helper that stream() also uses.
 */
beforeEach(function (): void {
    config(['services.scalewayai.api_key' => 'test-key']);
    config(['chatvia.ai.provider' => 'scaleway']);

    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => 'hi']]],
    ])]);
});

it('defaults reasoning_effort to none for Scaleway', function (): void {
    app(LlmProviderInterface::class)->completion(['model' => 'qwen3.6-35b-a3b', 'messages' => []]);

    Http::assertSent(fn ($request): bool => ($request->data()['reasoning_effort'] ?? null) === 'none');
});

it('preserves an explicit reasoning_effort set by the caller', function (): void {
    app(LlmProviderInterface::class)->completion([
        'model' => 'qwen3.6-35b-a3b',
        'messages' => [],
        'reasoning_effort' => 'high',
    ]);

    Http::assertSent(fn ($request): bool => ($request->data()['reasoning_effort'] ?? null) === 'high');
});

it('never sends reasoning_effort to non-qwen Scaleway models', function (string $model): void {
    // These instruct models 400 on an unknown reasoning_effort field
    // (regression that hit the analytics job in prod).
    app(LlmProviderInterface::class)->completion(['model' => $model, 'messages' => []]);

    Http::assertSent(fn ($request): bool => ! array_key_exists('reasoning_effort', $request->data()));
})->with([
    'mistral-small-3.2-24b-instruct-2506',
    'gpt-oss-120b',
]);

it('leaves non-Scaleway providers untouched', function (): void {
    config(['chatvia.ai.provider' => 'mistral']);
    config(['services.mistral.api_key' => 'test-key']);

    app(LlmProviderInterface::class)->completion(['model' => 'mistral-small', 'messages' => []]);

    Http::assertSent(fn ($request): bool => ! array_key_exists('reasoning_effort', $request->data()));
});
