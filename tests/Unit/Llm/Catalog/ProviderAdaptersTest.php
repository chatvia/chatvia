<?php

use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenAiCompatible;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Each provider kind has one adapter: its address, key, chat client and how
 * its model list is read (each provider says different things about its
 * models; what a list leaves out, the adapter may know). Adding a listed
 * model to the catalog keeps its alias unique across providers.
 */
function adapterFor(LlmProviderEnum $kind): ProviderAdapter
{
    return app(ProviderAdapters::class)->for($kind);
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, RemoteModelData>
 */
function listedModels(LlmProviderEnum $kind, array $rows): array
{
    Http::fake(['*' => Http::response(['data' => $rows])]);

    return adapterFor($kind)->models(LlmProvider::factory()->kind($kind)->make())->keyBy('id')->all();
}

it('has an adapter for every kind but Anthropic, in picker order', function (): void {
    expect(array_map(fn (ProviderAdapter $adapter): string => $adapter->kind()->value, app(ProviderAdapters::class)->all()))
        ->toBe(['openrouter', 'openai', 'google', 'mistral', 'groq', 'scaleway', 'custom'])
        ->and(app(ProviderAdapters::class)->has(LlmProviderEnum::ANTHROPIC))->toBeFalse();
});

it('builds each kind\'s chat client at its own or default address', function (LlmProviderEnum $kind, string $client, string $address): void {
    $provider = LlmProvider::factory()->kind($kind)->make();

    expect(adapterFor($kind)->client($provider))->toBeInstanceOf($client)
        ->and(adapterFor($kind)->baseUrl($provider))->toBe($address);
})->with([
    [LlmProviderEnum::OPENROUTER, OpenRouter::class, 'https://openrouter.ai/api/v1'],
    [LlmProviderEnum::OPENAI, OpenAiCompatible::class, 'https://api.openai.com/v1'],
    [LlmProviderEnum::MISTRAL, Mistral::class, 'https://api.mistral.ai/v1'],
    [LlmProviderEnum::SCALEWAY, Scaleway::class, 'https://api.scaleway.ai/v1'],
    [LlmProviderEnum::CUSTOM, OpenAiCompatible::class, 'https://llm.test/v1'],
]);

it('reads names, context, prices and features from OpenRouter', function (): void {
    $models = listedModels(LlmProviderEnum::OPENROUTER, [[
        'id' => 'mistralai/mistral-small',
        'name' => 'Mistral Small',
        'context_length' => 128_000,
        'pricing' => ['prompt' => '0.0000001', 'completion' => '0.0000003'],
        'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']],
        'supported_parameters' => ['tools'],
    ], [
        'id' => 'openai/text-embedding-3-small',
        'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['embeddings']],
    ]]);

    expect(array_keys($models))->toBe(['mistralai/mistral-small'])
        ->and($models['mistralai/mistral-small'])->toEqual(new RemoteModelData(
            id: 'mistralai/mistral-small',
            name: 'Mistral Small',
            contextWindow: 128_000,
            inputPricePerMillion: 0.1,
            outputPricePerMillion: 0.3,
            features: ['vision' => false, 'function_calling' => true],
        ));
});

it('reads the capabilities Mistral reports and drops its embedding models', function (): void {
    $models = listedModels(LlmProviderEnum::MISTRAL, [
        ['id' => 'mistral-large-latest', 'max_context_length' => 131_072, 'capabilities' => ['completion_chat' => true, 'function_calling' => true, 'vision' => false]],
        ['id' => 'mistral-embed', 'capabilities' => ['completion_chat' => false]],
    ]);

    expect(array_keys($models))->toBe(['mistral-large-latest'])
        ->and($models['mistral-large-latest']->contextWindow)->toBe(131_072)
        ->and($models['mistral-large-latest']->features)->toBe(['vision' => false, 'function_calling' => true]);
});

it('fills in what Scaleway\'s bare list leaves out, and drops its non-chat models', function (): void {
    $models = listedModels(LlmProviderEnum::SCALEWAY, [
        ['id' => 'mistral-small-3.2-24b-instruct-2506', 'object' => 'model', 'owned_by' => 'scaleway'],
        ['id' => 'bge-multilingual-gemma2', 'object' => 'model', 'owned_by' => 'scaleway'],
        ['id' => 'qwen3-embedding-8b', 'object' => 'model', 'owned_by' => 'scaleway'],
        ['id' => 'a-model-scaleway-added-later', 'object' => 'model', 'owned_by' => 'scaleway'],
    ]);

    expect(array_keys($models))->toBe(['a-model-scaleway-added-later', 'mistral-small-3.2-24b-instruct-2506'])
        ->and($models['mistral-small-3.2-24b-instruct-2506']->contextWindow)->toBe(128_000)
        ->and($models['mistral-small-3.2-24b-instruct-2506']->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and($models['a-model-scaleway-added-later']->contextWindow)->toBeNull();
});

it('lists a Scaleway project at its own address', function (): void {
    Http::fake(['*' => Http::response(['data' => []])]);
    $provider = LlmProvider::factory()->kind(LlmProviderEnum::SCALEWAY)->make(['base_url' => 'https://api.scaleway.ai/project-x/v1/']);

    adapterFor(LlmProviderEnum::SCALEWAY)->models($provider);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.ai/project-x/v1/models');
});

it('keeps only the chat models of a bare list, named as chat calls take them', function (LlmProviderEnum $kind, array $rows, array $kept): void {
    expect(array_keys(listedModels($kind, $rows)))->toBe($kept);
})->with([
    'gemini drops its prefix' => [LlmProviderEnum::GOOGLE, [['id' => 'models/gemini-chat'], ['id' => 'models/text-embedding-004']], ['gemini-chat']],
    'openai drops speech' => [LlmProviderEnum::OPENAI, [['id' => 'gpt-chat'], ['id' => 'whisper-1'], ['id' => 'tts-1']], ['gpt-chat']],
    'groq drops whisper' => [LlmProviderEnum::GROQ, [['id' => 'llama-3.3-70b-versatile', 'context_window' => 131_072], ['id' => 'whisper-large-v3-turbo']], ['llama-3.3-70b-versatile']],
]);

it('reads the context size a vLLM server lists', function (): void {
    expect(listedModels(LlmProviderEnum::CUSTOM, [['id' => 'qwen3:8b', 'max_model_len' => 32_768]])['qwen3:8b']->contextWindow)->toBe(32_768);
});

it('fails on a refused key', function (): void {
    Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

    expect(fn (): Collection => adapterFor(LlmProviderEnum::OPENAI)->models(LlmProvider::factory()->kind(LlmProviderEnum::OPENAI)->make()))
        ->toThrow(RequestException::class);
});

it('names the Settings → AI service a provider\'s key also runs', function (): void {
    config(['services.mistral.api_key' => 'mistral-key', 'services.mistral.base_url' => 'https://mistral.test/v1']);

    $settings = adapterFor(LlmProviderEnum::MISTRAL)->fromSettings();

    expect(adapterFor(LlmProviderEnum::SCALEWAY)->settingsConfig()['api_key'])->toBe('services.scalewayai.api_key')
        ->and(adapterFor(LlmProviderEnum::OPENROUTER)->settingsConfig())->toBe([])
        ->and($settings->api_key)->toBe('mistral-key')
        ->and(adapterFor(LlmProviderEnum::MISTRAL)->baseUrl($settings))->toBe('https://mistral.test/v1');
});

it('adds a model to the catalog under a unique alias', function (): void {
    $first = LlmProvider::factory()->create();
    $second = LlmProvider::factory()->create();
    $add = app(AddLlmModelAction::class);

    $one = $add->execute($first, new RemoteModelData('shared-model', 'Shared model', contextWindow: 32_000));
    $two = $add->execute($second, new RemoteModelData('shared-model', 'Shared model'));

    expect($one->external_name)->toBe('shared-model')
        ->and($one->context_window)->toBe(32_000)
        ->and($two->external_name)->toBe("shared-model-{$second->id}")
        ->and($add->execute($first, new RemoteModelData('shared-model', 'Renamed'))->is($one))->toBeTrue();
});
