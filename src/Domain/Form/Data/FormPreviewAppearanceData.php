<?php

declare(strict_types=1);

namespace Domain\Form\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The parts of the agent's chat widget look the Forms page preview copies:
 * header title, logo, avatar, brand colour, light/dark theme, the composer's
 * placeholder and mic, and the "Powered by" credit, so a form is previewed the
 * way visitors will see it in the chat.
 */
#[TypeScript]
class FormPreviewAppearanceData extends Data
{
    /** The brand colour the Setup page starts from when none is saved. */
    private const string DEFAULT_COLOR_THEME = 'black';

    private const int DEFAULT_COLOR_SHADE = 700;

    public function __construct(
        public ?string $title,
        public ?string $logo_url,
        public ?string $avatar_url,
        public string $color_theme,
        public int $color_shade,
        public string $theme,
        public ?string $composer_placeholder,
        public bool $voice_input,
        public bool $show_branding,
    ) {}

    /**
     * @param  bool  $transcriberEnabled  whether speech-to-text is set up; the
     *                                    widget shows the mic only then
     */
    public static function fromAgent(Agent $agent, bool $transcriberEnabled): self
    {
        $agent->loadSettingsGroups(['appearance', 'widget']);

        $appearance = $agent->settings('appearance');
        $widget = $agent->settings('widget');

        return new self(
            title: ((string) ($appearance['chat_window_title'] ?? '')) ?: $agent->name,
            logo_url: ((string) ($appearance['logo'] ?? '')) ?: null,
            avatar_url: ((string) ($appearance['avatar'] ?? '')) ?: null,
            color_theme: ((string) ($appearance['color_theme'] ?? '')) ?: self::DEFAULT_COLOR_THEME,
            color_shade: (int) ($appearance['color_shade'] ?? self::DEFAULT_COLOR_SHADE),
            theme: in_array($widget['theme'] ?? null, ['light', 'dark'], true) ? $widget['theme'] : 'auto',
            composer_placeholder: ((string) ($appearance['composer_placeholder'] ?? '')) ?: null,
            voice_input: $transcriberEnabled && (bool) ($appearance['allow_voice_input'] ?? true),
            show_branding: config('chatvia.powered_by') === true,
        );
    }
}
