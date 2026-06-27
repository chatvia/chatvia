<?php

namespace Domain\Agent\Data\Settings;

use Spatie\LaravelData\Data;

/**
 * The owner's manual per-language edits of the appearance text strings (the
 * `text_overrides` setting). Built from UpdateAppearanceTranslationsRequest,
 * already cleaned to non-empty values keyed by field then locale.
 */
class AppearanceOverridesData extends Data
{
    /**
     * @param  array<string, array<string, string>>  $overrides field => (locale => text)
     */
    public function __construct(
        public array $overrides,
    ) {}
}
