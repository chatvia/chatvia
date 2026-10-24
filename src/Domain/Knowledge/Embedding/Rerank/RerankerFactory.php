<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Str;

/**
 * Builds the reranker set in the admin panel (Settings → AI models), with
 * the keys of Settings → AI providers:
 *
 *  - `openrouter`: OpenRouter's `/rerank`, with the OpenRouter key;
 *  - `cohere`: Cohere's own API, with the Cohere key;
 *  - `custom`: a self-hosted server with the same API (vLLM, Infinity, …);
 *  - `none`: no reranker; callers use a basic similarity check instead.
 *
 * `auto` is the first connected provider that reranks: OpenRouter, then
 * Cohere, else none.
 */
class RerankerFactory
{
    /** The drivers `chatvia.ai.reranker` accepts besides `auto`. */
    public const array DRIVERS = ['openrouter', 'cohere', 'custom', 'none'];

    public function make(string $driver): Reranker
    {
        return $this->draft($driver);
    }

    /**
     * A reranker with a key, model or address that is not saved yet; each
     * one left null is the saved one. The admin setup checks what is typed
     * with it before it saves anything.
     */
    public function draft(string $driver, ?string $apiKey = null, ?string $model = null, ?string $url = null): Reranker
    {
        return match ($driver) {
            'openrouter' => $this->openRouter($apiKey, $model),
            'cohere' => $this->cohere($apiKey, $model),
            'custom' => $this->custom($apiKey, $model, $url),
            default => new NullReranker,
        };
    }

    public function autoDriver(): string
    {
        return AiProvider::firstConnectedFor(AiCapability::Reranking)?->value ?? 'none';
    }

    private function openRouter(?string $apiKey, ?string $model): Reranker
    {
        return new CompatibleReranker(
            baseUrl: 'https://openrouter.ai/api/v1',
            apiKey: $apiKey ?? config(AiProvider::OpenRouter->apiKeyConfig()),
            model: (string) ($model ?? config('chatvia.ai.rerank.openrouter_model') ?: RerankModelCatalog::DEFAULT_OPENROUTER),
            timeoutSeconds: $this->timeout(),
        );
    }

    private function cohere(?string $apiKey, ?string $model): Reranker
    {
        return new CompatibleReranker(
            baseUrl: Str::rtrim((string) config('services.cohere.api_endpoint', 'https://api.cohere.com/v2'), '/'),
            apiKey: $apiKey ?? config('services.cohere.api_key'),
            model: (string) ($model ?? config('services.cohere.rerank_model') ?: 'rerank-v4.0-pro'),
            extraPayload: ['max_tokens_per_doc' => 1000],
            timeoutSeconds: $this->timeout(),
        );
    }

    private function custom(?string $apiKey, ?string $model, ?string $url): Reranker
    {
        return new CompatibleReranker(
            baseUrl: Str::rtrim((string) ($url ?? config('chatvia.ai.rerank.custom.url')), '/'),
            apiKey: $apiKey ?? config('chatvia.ai.rerank.custom.key'),
            model: (string) ($model ?? config('chatvia.ai.rerank.custom.model')),
            keyRequired: false,
            timeoutSeconds: $this->timeout(),
        );
    }

    private function timeout(): int
    {
        return max(1, (int) config('chatvia.ai.rerank.timeout', 10));
    }
}
