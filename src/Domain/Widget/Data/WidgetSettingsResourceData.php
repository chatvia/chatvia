<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full settings payload the embedded widget reads at boot — appearance,
 * widget chrome, branding, and the agent's starter prompts.
 */
#[TypeScript]
class WidgetSettingsResourceData extends Data
{
    public function __construct(
        public bool $protected,
        public string $title,
        public string $logo_url,
        public ?string $avatar_url,
        public ?string $welcome_message,
        public ?string $color_theme,
        public ?string $color_shade,
        /** @var array<int, mixed> */
        public array $bubbles,
        public ?string $position,
        public ?bool $show_on_load,
        public ?int $show_on_load_delay,
        public WidgetSettingsBrandingData $branding,
        /** @var array<int, mixed> */
        public array $starters,
        public ?string $window_size,
        public ?string $theme,
    ) {}
}
