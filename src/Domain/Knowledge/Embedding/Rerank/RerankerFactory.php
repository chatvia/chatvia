<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;

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
        return match ($driver) {
            'openrouter' => $this->openRouter(),
            'cohere' => $this->cohere(),
            'custom' => $this->custom(),
            default => new NullReranker,
        };
    }

    public function autoDriver(): string
    {
        return AiProvider::firstConnectedFor(AiCapability::Reranking)?->value ?? 'none';
    }

    private function openRouter(): Reranker
    {
        return new CompatibleReranker(
            baseUrl: 'https://openrouter.ai/api/v1',
            apiKey: config(AiProvider::OpenRouter->apiKeyConfig()),
            model: (string) (config('chatvia.ai.rerank.openrouter_model') ?: RerankModelCatalog::DEFAULT_OPENROUTER),
            timeoutSeconds: $this->timeout(),
        );
    }

    private function cohere(): Reranker
    {
        return new CompatibleReranker(
            baseUrl: rtrim((string) config('services.cohere.api_endpoint', 'https://api.cohere.com/v2'), '/'),
            apiKey: config('services.cohere.api_key'),
            model: (string) (config('services.cohere.rerank_model') ?: 'rerank-v4.0-pro'),
            extraPayload: ['max_tokens_per_doc' => 1000],
            timeoutSeconds: $this->timeout(),
        );
    }

    private function custom(): Reranker
    {
        return new CompatibleReranker(
            baseUrl: rtrim((string) config('chatvia.ai.rerank.custom.url'), '/'),
            apiKey: config('chatvia.ai.rerank.custom.key'),
            model: (string) config('chatvia.ai.rerank.custom.model'),
            keyRequired: false,
            timeoutSeconds: $this->timeout(),
        );
    }

    private function timeout(): int
    {
        return max(1, (int) config('chatvia.ai.rerank.timeout', 10));
    }
}
