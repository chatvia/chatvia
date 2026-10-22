<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Enums\LlmProviderKind;

/**
 * Groq's OpenAI-compatible API: open models with very fast answers. Its
 * speech models are left out by id.
 */
final class GroqAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::Groq;
    }

    public function label(): string
    {
        return 'Groq';
    }

    public function description(): string
    {
        return 'Open models with very fast answers.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://api.groq.com/openai/v1';
    }

    public function keysUrl(): string
    {
        return 'https://console.groq.com/keys';
    }
}
