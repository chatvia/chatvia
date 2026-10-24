<?php

declare(strict_types=1);

use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelListing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reading a provider's model list tells a refused key from a server that
 * cannot be reached, also for providers that answer a wrong key with a
 * public list or a plain 400.
 */
beforeEach(function (): void {
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
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
        'openrouter.ai/api/v1/models/user' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
    ]);

    $list = app(ModelListing::class)->list(LlmProvider::factory()->make(['kind' => LlmProviderKind::OpenRouter, 'base_url' => null, 'api_key' => 'sk-or-wrong']));

    expect($list->status)->toBe(ModelListStatus::KeyRefused);
    Http::assertNotSent(fn (Request $request): bool => Str::endsWith($request->url(), '/models/user'));
});

it('lists only the OpenRouter models a provider the account allows serves', function (): void {
    Http::fake([
        'openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]),
        'openrouter.ai/api/v1/models/user' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
    ]);

    $list = app(ModelListing::class)->list(LlmProvider::factory()->make(['kind' => LlmProviderKind::OpenRouter, 'base_url' => null, 'api_key' => 'sk-or-v1-test']));

    expect($list->status)->toBe(ModelListStatus::Listed)
        ->and(collect($list->models)->pluck('id')->all())->toBe(['openai/gpt-oss-120b']);
    Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/models');
});

it('refuses a wrong Gemini key, which gets a 400, but not another 400', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push([['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT']]], 400)
        ->push(['error' => ['code' => 400, 'message' => 'Request contains an invalid argument.', 'status' => 'INVALID_ARGUMENT']], 400)]);

    $gemini = fn (): LlmProvider => LlmProvider::factory()->make(['kind' => LlmProviderKind::Google, 'base_url' => null, 'api_key' => 'wrong']);

    expect(app(ModelListing::class)->list($gemini())->status)->toBe(ModelListStatus::KeyRefused)
        ->and(app(ModelListing::class)->list($gemini())->status)->toBe(ModelListStatus::Failed);
});
