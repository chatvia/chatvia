<?php

use App\Admin\Settings\AiProviderCheck;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * The "Test connection" check of an AI provider account in Settings → AI
 * providers. For Azure Document Intelligence the key is tried against the
 * resource's own endpoint, and a free-tier resource gets a warning.
 */
it('checks an Azure key against its resource', function (): void {
    Http::fake([
        'good.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0]]),
        'bad.cognitiveservices.azure.com/*' => Http::response(['error' => ['code' => '401']], 401),
    ]);

    $check = app(AiProviderCheck::class);

    expect($check->check(AiProvider::Azure, 'az-key', 'https://good.cognitiveservices.azure.com')->connected)->toBeTrue()
        ->and($check->check(AiProvider::Azure, 'az-key', 'https://bad.cognitiveservices.azure.com')->keyRefused)->toBeTrue()
        ->and($check->check(AiProvider::Azure, 'az-key')->message)->toBe('Add the endpoint of your Azure resource too.');
});

it('warns when the Azure resource is on the free tier', function (): void {
    Http::fake([
        'free.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0, 'limit' => 500]]),
        'paid.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0, 'limit' => 5000]]),
    ]);

    $check = app(AiProviderCheck::class);
    $free = $check->check(AiProvider::Azure, 'az-key', 'https://free.cognitiveservices.azure.com');

    expect($free->connected)->toBeTrue()
        ->and($free->warning)->toContain('free tier (F0)')->toContain('first 2 pages')
        ->and($check->check(AiProvider::Azure, 'az-key', 'https://paid.cognitiveservices.azure.com')->warning)->toBeNull();
});

it('checks an Azure OpenAI key against the v1 API of the endpoint typed', function (): void {
    Http::fake([
        'good.openai.azure.com/openai/v1/models' => Http::response(['data' => []]),
        'bad.openai.azure.com/openai/v1/models' => Http::response(['error' => ['code' => '401']], 401),
    ]);

    $check = app(AiProviderCheck::class);

    expect($check->check(AiProvider::AzureOpenAi, 'az-key', 'https://good.openai.azure.com')->connected)->toBeTrue()
        ->and($check->check(AiProvider::AzureOpenAi, 'az-key', 'https://bad.openai.azure.com')->keyRefused)->toBeTrue()
        ->and($check->check(AiProvider::AzureOpenAi, 'az-key')->message)->toBe('Add the endpoint of your Azure OpenAI resource too.');
});
