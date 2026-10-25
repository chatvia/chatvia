<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * The Chat Widget on every agent of this install: the extra sites any widget
 * may load on, where its script loads from, how many replies an agent's
 * widget may stream, and whether its header says the visitor is talking to
 * an AI assistant.
 */
class WidgetSettings extends AdminSettings
{
    #[ConfigKey('chatvia.widget.extra_allowed_origins')]
    public ?string $allowed_origins = null;

    #[ConfigKey('chatvia.widget.script_url_embed')]
    public ?string $script_url = null;

    #[ConfigKey('chatvia.widget.stream_per_agent_per_minute')]
    public ?int $stream_per_minute = null;

    #[ConfigKey('chatvia.widget.stream_per_agent_per_hour')]
    public ?int $stream_per_hour = null;

    #[ConfigKey('chatvia.widget.ai_disclosure')]
    public ?bool $ai_disclosure = null;

    public static function group(): string
    {
        return 'widget';
    }
}
