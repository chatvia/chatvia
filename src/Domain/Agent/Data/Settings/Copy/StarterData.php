<?php

namespace Domain\Agent\Data\Settings\Copy;

use Domain\Agent\ValueObjects\TranslatableText;
use Spatie\LaravelData\Data;

/**
 * One conversation starter as the owner edits it and as it is stored: a
 * stable id (null for a new starter), the base text, an optional Heroicons
 * name, the AI translations and the owner's manual per-language edits
 * (locale → text).
 */
class StarterData extends Data
{
    /**
     * @param  array<string, string>  $translations
     * @param  array<string, string>  $overrides
     */
    public function __construct(
        public ?string $id,
        public string $text,
        public ?string $icon = null,
        public array $translations = [],
        public array $overrides = [],
    ) {}

    public static function fromCopy(string $id, ?string $icon, TranslatableText $copy): self
    {
        return new self($id, (string) $copy->base, $icon, $copy->translations, $copy->overrides);
    }
}
