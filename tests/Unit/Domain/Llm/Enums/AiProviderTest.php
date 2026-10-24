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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

it('refuses a wrong OpenRouter key, whose model list is public', function (): void {
    Http::fake([
        'openrouter.ai/api/v1/key' => Http::response(['error' => ['message' => 'User not found.', 'code' => 401]], 401),
        'openrouter.ai/api/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
    ]);

    $list = app(ModelListing::class)->list(LlmProvider::factory()->make(['kind' => LlmProviderKind::OpenRouter, 'base_url' => null, 'api_key' => 'sk-or-wrong']));

    expect($list->status)->toBe(ModelListStatus::KeyRefused);
    Http::assertNotSent(fn (Request $request): bool => Str::endsWith($request->url(), '/models'));
});

it('refuses a wrong Gemini key, which gets a 400, but not another 400', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push([['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT']]], 400)
        ->push(['error' => ['code' => 400, 'message' => 'Request contains an invalid argument.', 'status' => 'INVALID_ARGUMENT']], 400)]);

    $gemini = fn (): LlmProvider => LlmProvider::factory()->make(['kind' => LlmProviderKind::Google, 'base_url' => null, 'api_key' => 'wrong']);

    expect(app(ModelListing::class)->list($gemini())->status)->toBe(ModelListStatus::KeyRefused)
        ->and(app(ModelListing::class)->list($gemini())->status)->toBe(ModelListStatus::Failed);
});
