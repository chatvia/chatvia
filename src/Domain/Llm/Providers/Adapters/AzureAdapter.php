<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Azure;
use Illuminate\Support\Collection;
use Illuminate\Support\Uri;

/**
 * Azure OpenAI: the models deployed in one Azure resource, through its
 * OpenAI-style v1 API (`{endpoint}/openai/v1`). Calls name a deployment,
 * which the admin types: the resource's model list gives the models it could
 * deploy, not its deployments, so it only checks the key and endpoint.
 */
final class AzureAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::Azure;
    }

    public function label(): string
    {
        return AiProvider::AzureOpenAi->label();
    }

    public function description(): string
    {
        return AiProvider::AzureOpenAi->description();
    }

    public function acceptsBaseUrl(): bool
    {
        return false;
    }

    public function keysUrl(): string
    {
        return AiProvider::AzureOpenAi->keysUrl();
    }

    /**
     * The v1 API of the resource's endpoint, however the admin pasted it
     * (`https://x.openai.azure.com`, `…/openai/v1/`, a Foundry
     * `https://x.services.ai.azure.com`): only its host is kept.
     */
    public function baseUrl(LlmProvider $provider): string
    {
        $endpoint = (string) ($provider->base_url ?: config(AiProvider::AzureOpenAi->baseUrlConfig() ?? ''));

        if (blank($endpoint)) {
            return '';
        }

        $uri = Uri::of($endpoint);

        return Uri::of("{$uri->scheme()}://{$uri->host()}")
            ->withPort($uri->port())
            ->withPath('openai/v1')
            ->value();
    }

    public function client(LlmProvider $provider): LlmProviderInterface
    {
        return new Azure($provider, $this->baseUrl($provider), $this->apiKey($provider) ?? '');
    }

    /** Reads the list only to check the key and endpoint: deployments are typed by name. */
    public function models(LlmProvider $provider): Collection
    {
        $this->client($provider)
            ->client()
            ->timeout(20)
            ->get($this->modelsUrl($provider))
            ->throw();

        return collect();
    }

    /** Whether the key and the resource's endpoint are both set. */
    public function isConnected(LlmProvider $provider): bool
    {
        return parent::isConnected($provider) && $this->baseUrl($provider) !== '';
    }

    public function modelNameHelp(): string
    {
        return 'The deployment name, as Azure shows it under Deployments. Name it after its model (for example gpt-4.1), so its features are known.';
    }
}
