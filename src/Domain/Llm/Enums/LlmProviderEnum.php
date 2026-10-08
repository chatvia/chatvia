<?php

namespace Domain\Llm\Enums;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Providers\Groq;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;

/**
 * The one list of LLM inference providers. The same slug is used everywhere:
 * `chatvia.ai.provider` (which provider the app sends chat calls to), the
 * `llms.provider` column (which provider serves a catalog model), and cost
 * tracking. The `llms.provider` CHECK constraint must allow every case.
 *
 * Adding a provider means:
 *   1. Add a case here.
 *   2. Add a forward migration that widens the `llms_provider_check` constraint.
 *   3. Ship the matching client in Domain\Llm\Providers and return it from providerClass().
 */
enum LlmProviderEnum: string
{
    case SCALEWAY = 'scaleway';
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case MISTRAL = 'mistral';
    case GOOGLE = 'google';
    case OPENROUTER = 'openrouter';
    case GROQ = 'groq';

    /**
     * The client the app uses to call this provider, or null when the app has
     * no client for it (those models are reached through another provider).
     *
     * @return class-string<LlmProviderInterface>|null
     */
    public function providerClass(): ?string
    {
        return match ($this) {
            self::SCALEWAY => Scaleway::class,
            self::MISTRAL => Mistral::class,
            self::OPENROUTER => OpenRouter::class,
            self::GROQ => Groq::class,
            self::OPENAI, self::ANTHROPIC, self::GOOGLE => null,
        };
    }

    /**
     * Whether the provider's chat API can read a whole PDF passed by URL
     * (a `document_url` message part).
     */
    public function readsDocuments(): bool
    {
        return $this === self::MISTRAL;
    }
}
