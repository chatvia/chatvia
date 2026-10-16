<?php

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Rerank\RerankerFactory;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Services\ModelListing;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * The map of what each AI provider does matches the code that does it: the
 * embedding services, the chat adapters and the drivers that "Automatic"
 * picks from. Reading a model list tells a refused key from a server that
 * cannot be reached.
 */
beforeEach(function (): void {
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('runs every embedding service on an account that makes embeddings', function (): void {
    foreach (EmbeddingProvider::cases() as $embedding) {
        expect($embedding->account()->can(AiCapability::Embeddings))->toBeTrue($embedding->value)
            ->and($embedding->apiKeyConfig())->toBe($embedding->account()->apiKeyConfig());
    }

    $accounts = array_unique(array_map(fn (EmbeddingProvider $embedding): string => $embedding->account()->value, EmbeddingProvider::cases()));
    $makers = array_map(fn (AiProvider $provider): string => $provider->value, array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->can(AiCapability::Embeddings)));

    expect(array_values(array_diff($makers, $accounts)))->toBe([]);
});

it('gives every chat provider an account with chat, and back', function (): void {
    foreach (app(ProviderAdapters::class)->all() as $adapter) {
        /** @var ProviderAdapter $adapter */
        $account = $adapter->account();

        if ($adapter->kind() === LlmProviderKind::Custom) {
            expect($account)->toBeNull();

            continue;
        }

        expect($account?->can(AiCapability::Chat))->toBeTrue($adapter->kind()->value)
            ->and($account?->chatKind())->toBe($adapter->kind());
    }
});

it('names drivers "Automatic" can pick, in its order', function (): void {
    expect(array_diff(
        array_map(fn (AiProvider $provider): string => $provider->value, array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->can(AiCapability::Reranking))),
        RerankerFactory::DRIVERS,
    ))->toBe([]);

    expect(AiProvider::firstConnectedFor(AiCapability::Speech))->toBeNull();

    connectAiProviders(AiProvider::OpenAi, AiProvider::Scaleway);

    expect(AiProvider::firstConnectedFor(AiCapability::Speech))->toBe(AiProvider::Scaleway);

    connectAiProviders(AiProvider::Cohere, AiProvider::OpenRouter);

    expect(AiProvider::firstConnectedFor(AiCapability::Reranking))->toBe(AiProvider::OpenRouter);
});

it('reads a model list, and tells a refused key from a server it cannot reach', function (): void {
    Http::fake([
        'llm.test/v1/models' => Http::response(['data' => [['id' => 'llama3.1:8b']]]),
        'refusing.test/v1/models' => Http::response('nope', 401),
    ]);

    $listed = app(ModelListing::class)->list(LlmProvider::factory()->make(['base_url' => 'https://llm.test/v1']));
    $refused = app(ModelListing::class)->list(LlmProvider::factory()->make(['base_url' => 'https://refusing.test/v1']));

    expect($listed->status)->toBe(ModelListStatus::Listed)
        ->and($listed->models[0]->id)->toBe('llama3.1:8b')
        ->and($refused->status)->toBe(ModelListStatus::KeyRefused)
        ->and($refused->keyAccepted())->toBeFalse()
        ->and($refused->message('Office', 'https://llm.test/v1'))->toBe('Office refused the API key. Check that you copied all of it.');

    Http::swap(new Factory(app('events')));
    Http::fake(fn (): never => throw new ConnectionException('down'));

    expect(app(ModelListing::class)->list(LlmProvider::factory()->make(['base_url' => 'https://llm.test/v1']))->status)->toBe(ModelListStatus::Unreachable);
});
