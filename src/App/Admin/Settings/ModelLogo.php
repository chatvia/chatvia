<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ModelsDevCatalog;

/**
 * The logo of a model: its lab's (who made it), from models.dev, else its
 * provider's. The app serves it ({@see ModelLogoController}), so the admin's
 * browser never calls models.dev. Wherever the admin panel names a model
 * (lists, choices, badges, the models table) it shows it before the name.
 */
final class ModelLogo
{
    /** The URL of a model's logo, as the provider names the model, or null when it has none. */
    public static function url(LlmProviderKind $kind, string $modelId): ?string
    {
        $logo = app(ModelsDevCatalog::class)->logo($kind, $modelId);

        return $logo !== null ? route('filament.admin.model-logo', ['logo' => $logo], absolute: false) : null;
    }

    /**
     * A model's name with its logo before it, as HTML for a choice's label,
     * a badge or a table cell; just the name when it has no logo. The logo
     * takes the colour of the text.
     */
    public static function label(string $name, LlmProviderKind $kind, string $modelId): string
    {
        $url = self::url($kind, $modelId);

        return $url !== null
            ? '<span class="cv-model-label">'.self::mark($url).'<span>'.e($name).'</span></span>'
            : e($name);
    }

    /**
     * The logo itself: a mask the text colour fills. Its URL is set on the
     * element, not through a CSS variable: a url() in a variable resolves
     * against the stylesheet, which Vite or a CDN serves from another host.
     */
    public static function mark(string $url): string
    {
        $image = 'url('.e($url).')';

        return '<span class="cv-model-logo" style="-webkit-mask-image: '.$image.'; mask-image: '.$image.'" aria-hidden="true"></span>';
    }

    /** A catalog model's label: its name, or the text given, with its logo. */
    public static function forLlm(Llm $llm, ?string $text = null): string
    {
        return self::label($text ?? $llm->name, $llm->provider->kind, $llm->name);
    }
}
