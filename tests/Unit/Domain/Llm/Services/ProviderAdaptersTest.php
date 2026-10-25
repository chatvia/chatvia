<?php

declare(strict_types=1);

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Anthropic;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenAi;
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
 * models; what a list leaves out, the adapter may know).
 */
function padAdapter(LlmProviderKind $kind): ProviderAdapter
{
    return app(ProviderAdapters::class)->for($kind);
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, RemoteModelData>
 */
function padListedModels(LlmProviderKind $kind, array $rows): array
{
    Http::fake(['*' => Http::response(['data' => $rows])]);

    return padAdapter($kind)->models(LlmProvider::factory()->kind($kind)->make())->keyBy('id')->all();
}

it('has an adapter for every kind, in picker order', function (): void {
    expect(array_map(fn (ProviderAdapter $adapter): string => $adapter->kind()->value, app(ProviderAdapters::class)->all()))
        ->toBe(['openrouter', 'openai', 'anthropic', 'google', 'mistral', 'groq', 'scaleway', 'azure', 'custom']);
});

it('builds each kind\'s chat client at its own or default address', function (LlmProviderKind $kind, string $client, string $address): void {
    $provider = LlmProvider::factory()->kind($kind)->make();

    expect(padAdapter($kind)->client($provider))->toBeInstanceOf($client)
        ->and(padAdapter($kind)->baseUrl($provider))->toBe($address);
})->with([
    [LlmProviderKind::OpenRouter, OpenRouter::class, 'https://openrouter.ai/api/v1'],
    [LlmProviderKind::OpenAi, OpenAi::class, 'https://api.openai.com/v1'],
    [LlmProviderKind::Anthropic, Anthropic::class, 'https://api.anthropic.com/v1'],
    [LlmProviderKind::Google, OpenAiCompatible::class, 'https://generativelanguage.googleapis.com/v1beta/openai'],
    [LlmProviderKind::Groq, OpenAiCompatible::class, 'https://api.groq.com/openai/v1'],
    [LlmProviderKind::Mistral, Mistral::class, 'https://api.mistral.ai/v1'],
    [LlmProviderKind::Scaleway, Scaleway::class, 'https://api.scaleway.ai/v1'],
    [LlmProviderKind::Custom, OpenAiCompatible::class, 'https://llm.test/v1'],
]);

it('reads names, context, prices and features from OpenRouter', function (): void {
    $models = padListedModels(LlmProviderKind::OpenRouter, [[
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
            features: ['vision' => false, 'files' => false, 'function_calling' => true, 'reasoning' => false],
        ));
});

it('reads the capabilities Mistral reports and drops its embedding models', function (): void {
    $models = padListedModels(LlmProviderKind::Mistral, [
        ['id' => 'mistral-large-latest', 'max_context_length' => 131_072, 'capabilities' => ['completion_chat' => true, 'function_calling' => true, 'vision' => false]],
        ['id' => 'mistral-embed', 'capabilities' => ['completion_chat' => false]],
    ]);

    expect(array_keys($models))->toBe(['mistral-large-latest'])
        ->and($models['mistral-large-latest']->features)->toBe(['vision' => false, 'function_calling' => true]);
});

it('fills in what Scaleway\'s bare list leaves out, and drops its non-chat models', function (): void {
    $models = padListedModels(LlmProviderKind::Scaleway, [
        ['id' => 'mistral-small-3.2-24b-instruct-2506', 'object' => 'model', 'owned_by' => 'scaleway'],
        ['id' => 'bge-multilingual-gemma2', 'object' => 'model', 'owned_by' => 'scaleway'],
        ['id' => 'qwen3-embedding-8b', 'object' => 'model', 'owned_by' => 'scaleway'],
        ['id' => 'a-model-scaleway-added-later', 'object' => 'model', 'owned_by' => 'scaleway'],
    ]);

    expect(array_keys($models))->toBe(['a-model-scaleway-added-later', 'mistral-small-3.2-24b-instruct-2506'])
        ->and($models['mistral-small-3.2-24b-instruct-2506']->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and($models['a-model-scaleway-added-later']->features)->toBe([]);
});

it('lists every Anthropic model on one page, by its display name, with its key header', function (): void {
    config(['services.anthropic.api_key' => 'anthropic-key']);

    $models = padListedModels(LlmProviderKind::Anthropic, [
        ['type' => 'model', 'id' => 'claude-sonnet-5-5', 'display_name' => 'Claude Sonnet 5.5', 'created_at' => '2026-09-01T00:00:00Z'],
        ['type' => 'model', 'id' => 'claude-haiku-5-5', 'display_name' => 'Claude Haiku 5.5'],
    ]);

    expect(array_keys($models))->toBe(['claude-haiku-5-5', 'claude-sonnet-5-5'])
        ->and($models['claude-sonnet-5-5'])->toEqual(new RemoteModelData(
            id: 'claude-sonnet-5-5',
            name: 'Claude Sonnet 5.5',
            features: ['vision' => true, 'function_calling' => true],
        ));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/models?limit=1000'
        && $request->header('x-api-key') === ['anthropic-key']
        && $request->header('anthropic-version') === ['2023-06-01']
        && ! $request->hasHeader('Authorization'));
});

it('lists a Scaleway project at its own address', function (): void {
    Http::fake(['*' => Http::response(['data' => []])]);
    $provider = LlmProvider::factory()->kind(LlmProviderKind::Scaleway)->make(['base_url' => 'https://api.scaleway.ai/project-x/v1/']);

    padAdapter(LlmProviderKind::Scaleway)->models($provider);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.ai/project-x/v1/models');
});

it('keeps only the chat models of a bare list, named as chat calls take them', function (LlmProviderKind $kind, array $rows, array $kept): void {
    expect(array_keys(padListedModels($kind, $rows)))->toBe($kept);
})->with([
    'gemini drops its prefix' => [LlmProviderKind::Google, [['id' => 'models/gemini-chat'], ['id' => 'models/text-embedding-004']], ['gemini-chat']],
    'openai drops speech' => [LlmProviderKind::OpenAi, [['id' => 'gpt-chat'], ['id' => 'whisper-1'], ['id' => 'tts-1']], ['gpt-chat']],
    'groq drops whisper' => [LlmProviderKind::Groq, [['id' => 'llama-3.3-70b-versatile', 'context_window' => 131_072], ['id' => 'whisper-large-v3-turbo']], ['llama-3.3-70b-versatile']],
]);

it('fails on a refused key', function (): void {
    Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

    expect(fn (): Collection => padAdapter(LlmProviderKind::OpenAi)->models(LlmProvider::factory()->kind(LlmProviderKind::OpenAi)->make()))
        ->toThrow(RequestException::class);
});

it('calls with the key and address of the provider\'s account', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.mistral.base_url' => 'https://mistral.test/v1',
    ]);

    $mistral = padAdapter(LlmProviderKind::Mistral);
    $row = $mistral->standalone();

    expect($mistral->account())->toBe(AiProvider::Mistral)
        ->and($mistral->apiKey($row))->toBe('mistral-key')
        ->and($mistral->baseUrl($row))->toBe('https://mistral.test/v1')
        ->and($mistral->isConnected($row))->toBeTrue();
});

it('keeps Gemini\'s chat address apart from the native address of its account', function (): void {
    // Gemini's account address is the native API its embeddings call; chat keeps its own.
    config(['services.gemini.api_key' => 'gemini-key', 'services.gemini.api_endpoint' => 'https://gemini.test/v1beta']);

    $google = padAdapter(LlmProviderKind::Google);

    expect($google->baseUrl($google->standalone()))->toBe('https://generativelanguage.googleapis.com/v1beta/openai');
});

it('is not connected while its account has no key', function (): void {
    config(['services.groq.api_key' => null]);

    $groq = padAdapter(LlmProviderKind::Groq);

    expect($groq->isConnected($groq->standalone()))->toBeFalse();
});

it('prefers a key typed but not saved yet, as a check before saving sends', function (): void {
    config(['services.mistral.api_key' => 'mistral-key']);

    expect(padAdapter(LlmProviderKind::Mistral)->apiKey(new LlmProvider(['kind' => LlmProviderKind::Mistral, 'api_key' => 'typed'])))->toBe('typed');
});

it('gives a server of your own no account: its row holds its key, and it may have none', function (): void {
    $custom = padAdapter(LlmProviderKind::Custom);

    expect($custom->account())->toBeNull()
        ->and($custom->isConnected(LlmProvider::factory()->make(['api_key' => null])))->toBeTrue();
});
