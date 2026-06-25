<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Widget\Data\Settings\WidgetSettingsBrandingData;
use Domain\Widget\Data\Settings\WidgetSettingsResourceData;

/**
 * Assemble the settings payload the embedded widget loads at boot —
 * appearance + widget chrome + starters, plus the "Powered by Chatvia"
 * branding block.
 */
class BuildWidgetSettingsAction
{
    public function execute(Agent $agent): WidgetSettingsResourceData
    {
        // Single round-trip for all settings groups + starters; previously
        // ran separate SELECTs on every widget boot.
        $agent->loadMissing(['starterSet', 'team.owner']);
        $agent->loadSettingsGroups(['widget', 'appearance', 'bar', 'surfaces']);

        $widget = $agent->settings('widget');
        $appearance = $agent->settings('appearance');
        $bar = $agent->settings('bar');

        // Workspace-wide: the "remove branding" add-on hides the badge across
        // every agent. Drives both the legacy branding.enabled flag (so cached
        // widget bundles still obey it) and the dedicated chatvia_branding field.
        $showsBranding = $agent->showsChatviaBranding();

        return new WidgetSettingsResourceData(
            protected: $agent->is_public === false,
            // Per-surface kill switches. The embed reads its own mode (bubble →
            // widget_enabled, bar → bar_enabled) and doesn't mount when its
            // surface is off, so disabling one stops it showing on the host page.
            widget_enabled: $agent->surfaceEnabled(ConversationSource::Widget),
            bar_enabled: $agent->surfaceEnabled(ConversationSource::Bar),
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
                enabled: $showsBranding,
                name: 'Powered by ChatVia.ai',
                url: 'https://chatvia.ai',
                logo_url: asset('images/logo.svg'),
            ),
            chatvia_branding: $showsBranding,
            starters: $agent->resolvedStarters(),
            window_size: $widget['window_size'] ?? null,
            theme: $widget['theme'] ?? null,
            keyboard_trigger: $bar['trigger'] ?? null,
            bar_placement: $bar['placement'] ?? null,
            composer_placeholder: $appearance['composer_placeholder'] ?? null,
            privacy_policy_url: $appearance['privacy_policy_url'] ?? null,
            handoff_enabled: ($widget['handoff_enabled'] ?? true) && ($agent->team?->hasCapability(CapabilityEnum::HANDOFF) ?? false),
            allow_file_uploads: (bool) ($appearance['allow_file_uploads'] ?? true),
            allow_voice_input: (bool) ($appearance['allow_voice_input'] ?? true),
            max_files_per_chat: (int) config('chatvia.agent.files.upload.max_files'),
        );
    }
}
