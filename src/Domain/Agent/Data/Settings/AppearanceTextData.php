<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Settings;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * The three localizable appearance strings (the `appearance` model-settings
 * group). Built from SaveAppearanceTextRequest; `toFields()` is the non-empty
 * base-text map handed to TextTranslator.
 */
class AppearanceTextData extends Data
{
    public function __construct(
        public string $welcome_message,
        public string $chat_window_title,
        public string $composer_placeholder,
    ) {}

    /**
     * Non-empty base strings keyed by their setting key — the inputs the
     * translator localizes. Empty fields are dropped (nothing to translate).
     *
     * @return array<string, string>
     */
    public function toFields(): array
    {
        return array_filter([
            'chat_window_title' => Str::trim($this->chat_window_title),
            'welcome_message' => Str::trim($this->welcome_message),
            'composer_placeholder' => Str::trim($this->composer_placeholder),
        ], static fn (string $value): bool => $value !== '');
    }
}
