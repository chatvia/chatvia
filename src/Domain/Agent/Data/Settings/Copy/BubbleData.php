<?php

namespace Domain\Agent\Data\Settings\Copy;

use Domain\Agent\ValueObjects\TranslatableText;
use Spatie\LaravelData\Data;

/**
 * One widget chat-bubble prompt as the owner edits it and as it is stored:
 * the base text, the AI translations and the owner's manual per-language
 * edits (locale → text).
 */
class BubbleData extends Data
{
    /**
     * @param  array<string, string>  $translations
     * @param  array<string, string>  $overrides
     */
    public function __construct(
        public string $text,
        public array $translations = [],
        public array $overrides = [],
    ) {}

    public static function fromCopy(TranslatableText $copy): self
    {
        return new self($copy->base ?? '', $copy->translations, $copy->overrides);
    }
}
