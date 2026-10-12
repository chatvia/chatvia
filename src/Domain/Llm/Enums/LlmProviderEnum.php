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
enum LlmProviderEnum: string
{
    case OPENROUTER = 'openrouter';
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GOOGLE = 'google';
    case MISTRAL = 'mistral';
    case GROQ = 'groq';
    case SCALEWAY = 'scaleway';
    case CUSTOM = 'custom';

    /**
     * The adapter of this kind, or null while the app cannot call it yet.
     *
     * @return class-string<ProviderAdapter>|null
     */
    public function adapter(): ?string
    {
        return match ($this) {
            self::OPENROUTER => OpenRouterAdapter::class,
            self::OPENAI => OpenAiAdapter::class,
            self::GOOGLE => GeminiAdapter::class,
            self::MISTRAL => MistralAdapter::class,
            self::GROQ => GroqAdapter::class,
            self::SCALEWAY => ScalewayAdapter::class,
            self::CUSTOM => CustomServerAdapter::class,
            self::ANTHROPIC => null,
        };
    }
}
