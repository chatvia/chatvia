<?php

namespace Domain\Agent\Services;

use Domain\Agent\ValueObjects\TranslatableText;
use Illuminate\Support\Collection;

/**
 * Resolves the agent's three localizable appearance strings for a given visitor
 * language. Precedence per field: manual override > AI translation > base value
 * (see {@see TranslatableText}).
 * Used by every chat surface (widget settings payload + hosted/embedded chat)
 * so the visitor sees the title / welcome / placeholder in their own language
 * while the owner keeps full per-language control.
 */
class AppearanceTextResolver
{
    /** The appearance keys that carry per-locale translations. */
    public const array KEYS = ['chat_window_title', 'welcome_message', 'composer_placeholder', 'notice'];

    /**
     * @param  Collection<string, mixed>|array<string, mixed>  $appearance
     * @return array<string, string|null> the three keys resolved for $locale
     */
    public function resolve(Collection|array $appearance, ?string $locale): array
    {
        $appearance = $appearance instanceof Collection ? $appearance : collect($appearance);

        $translations = (array) ($appearance->get('text_translations') ?? []);
        $overrides = (array) ($appearance->get('text_overrides') ?? []);

        $resolved = [];

        foreach (self::KEYS as $key) {
            $base = $appearance->get($key);

            $resolved[$key] = new TranslatableText(
                base: $base === null ? null : (string) $base,
                translations: (array) ($translations[$key] ?? []),
                overrides: (array) ($overrides[$key] ?? []),
            )->in($locale);
        }

        return $resolved;
    }

    /**
     * Overlay the localized strings onto the full appearance collection. Surface
     * controllers pass the whole `appearance` payload straight to the frontend,
     * so this returns the same collection with only the three text keys swapped
     * for their localized values.
     *
     * @param  Collection<string, mixed>  $appearance
     * @return Collection<string, mixed>
     */
    public function localize(Collection $appearance, ?string $configuredLanguage, ?string $acceptLanguage): Collection
    {
        $locale = (! collect([null, '', 'auto'])->containsStrict($configuredLanguage))
            ? $configuredLanguage
            : $acceptLanguage;

        $resolved = array_filter(
            $this->resolve($appearance, $locale),
            static fn (?string $value): bool => $value !== null,
        );

        return $appearance->merge($resolved);
    }
}
