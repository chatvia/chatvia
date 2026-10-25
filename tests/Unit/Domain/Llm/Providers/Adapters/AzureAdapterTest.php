<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Adapters\AzureAdapter;
use Domain\Llm\Providers\Azure;
use Domain\Llm\Services\ModelListing;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Azure OpenAI calls the v1 API of the resource whose key and endpoint are
 * saved. Its models are deployments the admin types, so reading the list
 * only checks the key and endpoint.
 */
function azureRow(): LlmProvider
{
    return LlmProvider::factory()->kind(LlmProviderKind::Azure)->make();
}

it('calls the v1 API of the endpoint however it was pasted', function (string $endpoint): void {
    config(['services.azure_openai.endpoint' => $endpoint]);

    expect(app(AzureAdapter::class)->baseUrl(azureRow()))->toBe('https://res.openai.azure.com/openai/v1')
        ->and(app(AzureAdapter::class)->client(azureRow()))->toBeInstanceOf(Azure::class);
})->with([
    'the resource' => 'https://res.openai.azure.com',
    'with a slash' => 'https://res.openai.azure.com/',
    'with /openai' => 'https://res.openai.azure.com/openai',
    'the v1 API' => 'https://res.openai.azure.com/openai/v1/',
]);

it('keeps a Microsoft Foundry endpoint\'s own host', function (): void {
    config(['services.azure_openai.endpoint' => 'https://res.services.ai.azure.com/']);

    expect(app(AzureAdapter::class)->baseUrl(azureRow()))->toBe('https://res.services.ai.azure.com/openai/v1');
});

it('is connected only with both its key and its endpoint', function (?string $key, ?string $endpoint, bool $connected): void {
    config(['services.azure_openai.api_key' => $key, 'services.azure_openai.endpoint' => $endpoint]);

    expect(app(AzureAdapter::class)->isConnected(azureRow()))->toBe($connected);
})->with([
    'both' => ['az-key', 'https://res.openai.azure.com', true],
    'no endpoint' => ['az-key', null, false],
    'no key' => [null, 'https://res.openai.azure.com', false],
]);

it('reads the model list only to check the key, so deployments are typed by name', function (int $status, ModelListStatus $listed): void {
    config(['services.azure_openai.api_key' => 'az-key', 'services.azure_openai.endpoint' => 'https://res.openai.azure.com']);
    Http::fake(['res.openai.azure.com/openai/v1/models' => Http::response(['data' => [['id' => 'gpt-4.1']]], $status)]);

    expect(app(ModelListing::class)->list(azureRow())->status)->toBe($listed);

    Http::assertSent(fn (Request $request): bool => $request->header('api-key') === ['az-key']);
})->with([
    'key accepted' => [200, ModelListStatus::Empty],
    'key refused' => [401, ModelListStatus::KeyRefused],
]);
