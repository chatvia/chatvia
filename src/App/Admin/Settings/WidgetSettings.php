<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * How the Chat Widget looks on every agent of this install: for now, whether
 * its header says the visitor is talking to an AI assistant.
 */
class WidgetSettings extends AdminSettings
{
    #[ConfigKey('chatvia.widget.ai_disclosure')]
    public ?bool $ai_disclosure = null;

    public static function group(): string
    {
        return 'widget';
    }
}
