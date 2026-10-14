<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\AiProvider;
use Filament\Support\Icons\Heroicon;

/**
 * The picture of an AI provider in the admin panel: its logo when the app
 * has one, an icon otherwise. Settings → AI providers and the setup's
 * provider cards both use it, so a provider looks the same in each.
 */
final class AiProviderIcon
{
    /** Logos the app has; the other providers show an icon. */
    private const array LOGOS = [
        'azure' => 'images/llms/microsoft.svg',
        'openai' => 'images/llms/gpt.svg',
        'mistral' => 'images/llms/mistral.svg',
        'google' => 'images/llms/gemini.svg',
        'cohere' => 'images/llms/cohere.svg',
    ];

    /** The URL of its logo, or an icon. */
    public static function of(AiProvider $provider): string|Heroicon
    {
        if (isset(self::LOGOS[$provider->value])) {
            return asset(self::LOGOS[$provider->value]);
        }

        return match ($provider) {
            AiProvider::OpenRouter => Heroicon::OutlinedArrowsRightLeft,
            AiProvider::Groq => Heroicon::OutlinedBolt,
            AiProvider::Jina => Heroicon::OutlinedCubeTransparent,
            default => Heroicon::OutlinedCloud,
        };
    }
}
