<?php

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Scaleway's Qwen3 models think by default, which adds several seconds before
 * the first answer token. This pipeline is RAG, so reasoning is defaulted OFF
 * (`reasoning_effort=none`) for qwen models only — unless the caller asks for
 * it. Other Scaleway models (analytics / guardrail instruct models) 400 on an
 * unknown `reasoning_effort` field, so they must never receive it. completion()
 * goes through the fakeable Http client and Scaleway::preparePayload(), which
 * stream() uses too.
 */
function srClient(LlmProviderKind $kind = LlmProviderKind::Scaleway): LlmProviderInterface
{
    return app(LlmProviders::class)->forProvider(LlmProvider::factory()->kind($kind)->make());
}

beforeEach(function (): void {
    connectAiProviders();

    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => 'hi']]],
    ])]);
});

it('defaults reasoning_effort to none for Scaleway', function (): void {
    srClient()->completion(['model' => 'qwen3.6-35b-a3b', 'messages' => []]);

    Http::assertSent(fn (Request $request): bool => ($request->data()['reasoning_effort'] ?? null) === 'none');
});

it('preserves an explicit reasoning_effort set by the caller', function (): void {
    srClient()->completion([
        'model' => 'qwen3.6-35b-a3b',
        'messages' => [],
        'reasoning_effort' => 'high',
    ]);

    Http::assertSent(fn (Request $request): bool => ($request->data()['reasoning_effort'] ?? null) === 'high');
});

it('never sends reasoning_effort to non-qwen Scaleway models', function (string $model): void {
    // These instruct models answer 400 to an unknown reasoning_effort field.
    srClient()->completion(['model' => $model, 'messages' => []]);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('reasoning_effort', $request->data()));
})->with([
    'mistral-small-3.2-24b-instruct-2506',
    'gpt-oss-120b',
]);

it('leaves non-Scaleway providers untouched', function (): void {
    srClient(LlmProviderKind::Mistral)->completion(['model' => 'mistral-small', 'messages' => []]);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('reasoning_effort', $request->data()));
});
