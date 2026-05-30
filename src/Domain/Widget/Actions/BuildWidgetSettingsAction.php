<?php

namespace Domain\Widget\Actions;

use Domain\Agents\Models\Agent;
use Domain\Widget\Data\WidgetSettingsBrandingData;
use Domain\Widget\Data\WidgetSettingsResourceData;

/**
 * Assemble the settings payload the embedded widget loads at boot —
 * appearance + widget chrome + starters, plus the "Powered by Chatvia"
 * branding block.
 */
class BuildWidgetSettingsAction
{
    public function execute(Agent $agent): WidgetSettingsResourceData
    {
        $widget = $agent->settings('widget');
        $appearance = $agent->settings('appearance');

        return new WidgetSettingsResourceData(
            protected: $agent->is_public === false,
            title: (string) ($appearance['chat_window_title'] ?? ''),
            logo_url: (string) ($appearance['logo'] ?? ''),
            avatar_url: $appearance['avatar'] ?? null,
            welcome_message: $appearance['welcome_message'] ?? null,
            color_theme: $appearance['color_theme'] ?? null,
            color_shade: $appearance['color_shade'] ?? null,
            bubbles: $widget['bubbles'] ?? [],
            position: $widget['position'] ?? null,
            show_on_load: $widget['show_on_load'] ?? null,
            show_on_load_delay: $widget['show_on_load_delay'] ?? null,
            branding: new WidgetSettingsBrandingData(
                enabled: true,
                name: 'Powered by ChatVia.ai',
                url: 'https://chatvia.ai',
                logo_url: asset('images/logo.svg'),
            ),
            starters: $agent->resolvedStarters(),
            window_size: $widget['window_size'] ?? null,
            theme: $widget['theme'] ?? null,
        );
    }
}
