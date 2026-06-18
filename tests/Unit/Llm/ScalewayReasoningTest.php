<?php

use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;

/**
 * Scaleway reasoning models think by default, which adds several seconds before
 * the first answer token. This pipeline is RAG, so reasoning is defaulted OFF
 * (`reasoning_effort=none`) for Scaleway — unless the caller asks for it, and
 * never for `gpt-oss-*` (which rejects `none`). completion() goes through the
 * fakeable Http client, so it exercises the shared disableScalewayReasoning()
 * helper that stream() also uses.
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

it('never disables reasoning for gpt-oss models on Scaleway', function (): void {
    app(LlmProviderInterface::class)->completion(['model' => 'gpt-oss-120b', 'messages' => []]);

    Http::assertSent(fn ($request): bool => ! array_key_exists('reasoning_effort', $request->data()));
});

it('leaves non-Scaleway providers untouched', function (): void {
    config(['chatvia.ai.provider' => 'mistral']);
    config(['services.mistral.api_key' => 'test-key']);

    app(LlmProviderInterface::class)->completion(['model' => 'mistral-small', 'messages' => []]);

    Http::assertSent(fn ($request): bool => ! array_key_exists('reasoning_effort', $request->data()));
});
