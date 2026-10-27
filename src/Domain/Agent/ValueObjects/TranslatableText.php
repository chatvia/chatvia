<?php

namespace Domain\Agent\ValueObjects;

use Illuminate\Support\Str;

/**
 * A piece of owner-written copy shown to visitors in their own language: a
 * conversation starter, a widget chat bubble, or an appearance string (chat
 * title, welcome message, composer placeholder).
 *
 * It holds the base text the owner typed, the AI translations (locale → text)
 * and the owner's manual per-language edits (locale → text). For a visitor
 * language the manual edit wins over the AI translation, which wins over the
 * base text. Locales are two-letter codes ("de"); a regional or upper-case
 * visitor locale ("de-AT", "DE") resolves to the same entry.
 */
final readonly class TranslatableText
{
    /**
     * @param  array<string, string>  $translations  locale → AI translation
     * @param  array<string, string>  $overrides  locale → manual edit
     */
    public function __construct(
        public ?string $base,
        public array $translations = [],
        public array $overrides = [],
    ) {}

    /**
     * Read a stored entry in its `{ text, translations, overrides }` shape;
     * anything else reads as empty text.
     */
    public static function fromStored(mixed $stored): self
    {
        $stored = is_array($stored) ? $stored : [];

        return new self(
            base: (string) ($stored['text'] ?? ''),
            translations: self::stringMap($stored['translations'] ?? []),
            overrides: self::stringMap($stored['overrides'] ?? []),
        );
    }

    /**
     * The text for a visitor locale: the manual edit, else the AI translation,
     * else the base text. A missing or `auto` locale gets the base text.
     */
    public function in(?string $locale): ?string
    {
        $locale = self::normalizeLocale($locale);

        if ($locale === null) {
            return $this->base;
        }

        foreach ([$this->overrides[$locale] ?? null, $this->translations[$locale] ?? null] as $candidate) {
            if (filled($candidate)) {
                return $candidate;
            }
        }

        return $this->base;
    }

    /**
     * Whether an AI translation already exists for every given locale — when
     * true there is nothing new to translate.
     *
     * @param  list<string>  $locales
     */
    public function isTranslatedInto(array $locales): bool
    {
        if ($this->translations === []) {
            return false;
        }

        return array_all(
            $locales,
            fn (string $locale): bool => filled($this->translations[$locale] ?? null),
        );
    }

    /**
     * @param  array<string, string>  $translations
     */
    public function withTranslations(array $translations): self
    {
        return new self($this->base, $translations, $this->overrides);
    }

    /**
     * Reduce 'en-US' / 'EN' / 'auto' to a clean two-letter code, or null when
     * the locale doesn't name a language.
     */
    public static function normalizeLocale(?string $locale): ?string
    {
        $locale = Str::lower(Str::trim($locale ?? ''));

        if (collect(['', 'auto', '*'])->containsStrict($locale)) {
            return null;
        }

        return Str::substr($locale, 0, 2);
    }

    /**
     * Keep the non-empty, trimmed texts for the given languages only — how
     * manual edits are cleaned before they are stored.
     *
     * @param  array<string, string>  $languages  locale code → language name
     * @return array<string, string>
     */
    public static function onlyLanguages(mixed $map, array $languages): array
    {
        $clean = [];

        foreach ((array) $map as $locale => $value) {
            // A number is kept as its text; a list or other junk is dropped.
            $value = is_scalar($value) ? Str::trim((string) $value) : '';

            if ($value !== '' && isset($languages[(string) $locale])) {
                $clean[(string) $locale] = $value;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $map): array
    {
        return is_array($map) ? $map : [];
    }
}
