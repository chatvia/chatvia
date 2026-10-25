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
 * (lists, choices, badges, the models table) it shows it before the name;
 * a provider picked next to its models gets its own logo the same way.
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
        return self::withMark($name, self::url($kind, $modelId));
    }

    /**
     * A provider's name with its own logo before it, drawn like a model's;
     * just the name for a server of your own.
     */
    public static function forProvider(string $name, LlmProviderKind $kind): string
    {
        $logo = app(ModelsDevCatalog::class)->providerLogo($kind);

        return self::withMark($name, $logo !== null ? route('filament.admin.model-logo', ['logo' => $logo], absolute: false) : null);
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

    /**
     * The choices of a model picker, each name with its model's logo.
     *
     * @param  array<string, string>  $names  The name of each model, by its id at the provider.
     * @return array<string, string>
     */
    public static function labels(array $names, LlmProviderKind $kind): array
    {
        return collect($names)->map(fn (string $name, string|int $id): string => self::label($name, $kind, (string) $id))->all();
    }

    /**
     * A model's name with its lab's logo before it (`cohere`), for a model
     * on its lab's own API, which is no chat provider here.
     */
    public static function forLab(string $name, string $lab): string
    {
        return self::withMark($name, route('filament.admin.model-logo', ['logo' => app(ModelsDevCatalog::class)->labLogo($lab)], absolute: false));
    }

    /** A catalog model's label: its name for people, or the text given, with its logo. */
    public static function forLlm(Llm $llm, ?string $text = null): string
    {
        return self::label($text ?? $llm->displayName(), $llm->provider->kind, $llm->name);
    }

    private static function withMark(string $name, ?string $url): string
    {
        return $url !== null
            ? '<span class="cv-model-label">'.self::mark($url).'<span>'.e($name).'</span></span>'
            : e($name);
    }
}
