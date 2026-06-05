<?php

namespace Domain\Widget\Data\Settings;

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
        // Bar-mode keyboard launcher shortcut. One of:
        //   cmd+k | cmd+j | cmd+/ | / | shift+/
        // The widget parses this string in src/state/events.js and renders the
        // matching hint label inside the floating bar.
        public ?string $keyboard_trigger,
        // Bar-mode on-page anchor. One of:
        //   top | top-right | bottom | bottom-right
        // The widget maps each value to a placement class in _createBar so the
        // floating bar lands in the right corner of the viewport. `top` and
        // `bottom` are horizontally centered; the `-right` variants anchor to
        // the right edge.
        public ?string $bar_placement,
        // Placeholder shown inside the composer/input textarea. Distinct from
        // `welcome_message` (which is the first assistant message shown when a
        // thread is empty). The widget previously fell back to `welcome_message`
        // here, which surfaced a full greeting inside the tiny input.
        public ?string $composer_placeholder,
        // Disclaimer line shown below the composer (e.g. "AI can make mistakes,
        // check important info"). Optional — when null/empty the widget hides
        // the strip entirely.
        public ?string $chat_footer_text,
        // Whether visitors can hand off to a human (Live Chat). Widget-only; the
        // widget hides the "Talk to a human" affordance when false. Already
        // ANDed with the workspace's handoff capability on the server.
        public bool $handoff_enabled,
        // Whether to show the paperclip / attach-file button in the composer.
        // Owners turn this off when the agent shouldn't accept uploads (e.g.
        // FAQ-only, compliance). Default true.
        public bool $allow_file_uploads,
        // Whether to show the microphone / voice-input button in the composer.
        // Owners turn this off when the agent shouldn't expose speech-to-text.
        // Default true.
        public bool $allow_voice_input,
        // Per-chat upload ceiling (config: agent.files.upload.max_files).
        // Widget gates the paperclip button on this; the server enforces the
        // same value via MaxConversationAttachments so a forged request
        // still gets a 422.
        public int $max_files_per_chat,
    ) {}
}
