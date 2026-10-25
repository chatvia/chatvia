<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\AiProvider;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\generate_icon_html;

/**
 * The label of a choice in a service picker (reranker, documents, speech,
 * voice, embeddings): a provider's name with its logo before it, in its own
 * colours as on Settings → AI providers, or a choice that is no provider
 * (Self-hosted, Off) with an icon. HTML, for a select that allows it; a
 * model's label is {@see ModelLogo}'s.
 */
final class OptionLabel
{
    public static function provider(string $name, AiProvider $provider): string
    {
        return self::withIcon($name, AiProviderIcon::of($provider));
    }

    public static function icon(string $name, Heroicon $icon): string
    {
        return self::withIcon($name, $icon);
    }

    private static function withIcon(string $name, string|Heroicon $icon): string
    {
        return '<span class="cv-model-label">'.generate_icon_html($icon, size: IconSize::Small)?->toHtml().'<span>'.e($name).'</span></span>';
    }
}
