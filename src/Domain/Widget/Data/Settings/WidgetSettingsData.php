<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full settings payload the embedded widget reads at boot — appearance,
 * widget chrome, branding, and the agent's starter prompts.
 */
#[TypeScript]
class WidgetSettingsData extends Data
{
    public function __construct(
        public bool $protected,
        // Per-surface kill switches. The embed mounts only when its own surface is
        // enabled: bubble mode checks `widget_enabled`, bar mode checks
        // `bar_enabled`. Both default true server-side, so an older cached bundle
        // that ignores them behaves exactly as before.
        public bool $widget_enabled,
        public bool $bar_enabled,
        public string $title,
        public string $logo_url,
        public ?string $avatar_url,
        public ?string $welcome_message,
        public ?string $color_theme,
        public ?string $color_shade,
        /** @var array<int, mixed> */
        public array $bubbles,
        // Per-bubble per-locale maps, parallel to `bubbles` by index ({ locale:
        // text }). The widget localizes each bubble against its active language
        // and falls back to the resolved `bubbles` string above.
        /** @var array<int, array<string, string>> */
        public array $bubble_translations,
        public ?string $position,
        public ?bool $show_on_load,
        public ?int $show_on_load_delay,
        public WidgetSettingsBrandingData $branding,
        // Whether the "Powered by ChatVia.ai" badge should render: the install's
        // switch (Settings → Branding). The widget gates the footer on this (and
        // on branding.enabled, which is driven by the same value for
        // back-compat with cached bundles).
        public bool $chatvia_branding,
        /** @var array<int, mixed> */
        public array $starters,
        // Per-locale maps for the agent strings (title / welcome_message /
        // composer_placeholder), shape { field: { locale: text } }. The widget
        // resolves its active display language against these and falls back to
        // the resolved scalar fields above when a language has no translation.
        /** @var array<string, array<string, string>> */
        public array $text_translations,
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
        // thread is empty): the greeting is too long to show inside the small
        // input, so the widget never uses it as the placeholder.
        public ?string $composer_placeholder,
        // Optional short notice under the composer (what the agent covers,
        // that answers can be wrong); null shows nothing. Resolved for the
        // visitor's language like the strings above.
        public ?string $notice,
        // Optional link to the workspace's own privacy policy. When non-empty,
        // the widget shows a "By chatting, you agree to our privacy policy"
        // consent notice linking here; when null it shows nothing. The URL the
        // owner sets in Setup → Info — an extra layer of data-protection
        // transparency the customer controls per agent.
        public ?string $privacy_policy_url,
        // Whether visitors can hand off to a human (Live Chat). Widget-only; the
        // widget hides the "Talk to a human" affordance when false. Already
        // ANDed with the workspace's handoff capability on the server.
        public bool $handoff_enabled,
        // Whether to show the paperclip / attach-file button in the composer.
        // Owners turn this off when the agent shouldn't accept uploads (e.g.
        // FAQ-only, compliance). Default true.
        public bool $allow_file_uploads,
        // The MIME types the paperclip accepts: what the model for chat files
        // reads (PDF and/or PNG, JPEG, WebP, GIF). The server checks the same
        // list on upload.
        /** @var list<string> */
        public array $upload_accept,
        // Whether to show the microphone / voice-input button in the composer.
        // Owners turn this off when the agent shouldn't expose speech-to-text;
        // it is also off while no speech-to-text service is set up.
        public bool $allow_voice_input,
        // Whether to show the voice-conversation button (a live spoken call
        // with the agent). True only when the owner turned it on AND voice
        // conversations are set up on this install.
        public bool $voice_conversation,
        // Per-chat upload ceiling (config: agent.files.upload.max_files).
        // Widget gates the paperclip button on this; the server enforces the
        // same value via MaxConversationAttachments so a forged request
        // still gets a 422.
        public int $max_files_per_chat,
        // Where this server's live chat (Reverb) runs. The widget uses it for
        // any value its embed tag and its build do not set.
        public WidgetSettingsReverbData $reverb,
    ) {}
}
