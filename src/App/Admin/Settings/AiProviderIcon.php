<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;

/**
 * The logo of an AI provider, in its own colours. Settings → AI providers,
 * the setup's provider cards, the Status page and an agent's model picker
 * all use it, so a provider looks the same in each.
 */
final class AiProviderIcon
{
    /** The URL of its logo. */
    public static function of(AiProvider $provider): string
    {
        return asset(match ($provider) {
            AiProvider::Scaleway => 'images/llms/scaleway.svg',
            AiProvider::OpenRouter => 'images/llms/openrouter.svg',
            AiProvider::OpenAi => 'images/llms/gpt.svg',
            AiProvider::Mistral => 'images/llms/mistral.svg',
            AiProvider::Google => 'images/llms/gemini.svg',
            AiProvider::Groq => 'images/llms/groq.svg',
            AiProvider::Anthropic => 'images/llms/claude.svg',
            AiProvider::Cohere => 'images/llms/cohere.svg',
            AiProvider::Jina => 'images/llms/jina.svg',
            AiProvider::Voyage => 'images/llms/voyage.svg',
            AiProvider::Azure => 'images/llms/microsoft.svg',
        });
    }

    /** The URL of the logo of a chat provider kind, or null for a server of your own. */
    public static function forKind(LlmProviderKind $kind): ?string
    {
        $provider = AiProvider::forChatKind($kind);

        return $provider instanceof AiProvider ? self::of($provider) : null;
    }
}
