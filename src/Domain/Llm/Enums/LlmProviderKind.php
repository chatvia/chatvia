<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Domain\Llm\Providers\Adapters\CustomServerAdapter;
use Domain\Llm\Providers\Adapters\GeminiAdapter;
use Domain\Llm\Providers\Adapters\GroqAdapter;
use Domain\Llm\Providers\Adapters\MistralAdapter;
use Domain\Llm\Providers\Adapters\OpenAiAdapter;
use Domain\Llm\Providers\Adapters\OpenRouterAdapter;
use Domain\Llm\Providers\Adapters\ScalewayAdapter;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Services\ModelsDevCatalog;

/**
 * The kinds of LLM provider an admin can connect (`llm_providers.kind`, also
 * the slug cost tracking uses). Everything about a kind lives in its adapter
 * ({@see ProviderAdapter}); the order of the cases is the order of the
 * provider pickers. `custom` is any other server with an OpenAI-compatible
 * API (vLLM, Ollama, LM Studio, ...).
 *
 * Adding a provider means adding a case (the `llm_providers.kind` CHECK
 * constraint is built from the cases) and its adapter here.
 */
enum LlmProviderKind: string
{
    case OpenRouter = 'openrouter';
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Google = 'google';
    case Mistral = 'mistral';
    case Groq = 'groq';
    case Scaleway = 'scaleway';
    case Custom = 'custom';

    /**
     * The adapter of this kind, or null while the app cannot call it yet.
     *
     * @return class-string<ProviderAdapter>|null
     */
    public function adapter(): ?string
    {
        return match ($this) {
            self::OpenRouter => OpenRouterAdapter::class,
            self::OpenAi => OpenAiAdapter::class,
            self::Google => GeminiAdapter::class,
            self::Mistral => MistralAdapter::class,
            self::Groq => GroqAdapter::class,
            self::Scaleway => ScalewayAdapter::class,
            self::Custom => CustomServerAdapter::class,
            self::Anthropic => null,
        };
    }

    /**
     * This kind's provider in the models.dev catalog ({@see ModelsDevCatalog}),
     * which names them as these values do; null for a server of your own,
     * whose models are looked up among the catalog's general models.
     */
    public function catalogKey(): ?string
    {
        return $this === self::Custom ? null : $this->value;
    }
}
