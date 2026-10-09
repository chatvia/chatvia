<?php

namespace App\Http\Chat;

use Domain\Agent\Models\Agent;

/**
 * The page props every hosted-chat page shares (the standalone chat and the
 * iframe embed): the appearance with its per-language texts — the page picks
 * the visitor's language itself, so the header switcher can change it live —
 * and the owner's embed choices.
 */
final class HostedChatPageProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Agent $agent): array
    {
        $embed = $agent?->settings('embed');

        return [
            'appearance' => $agent?->settings('appearance'),
            'removeSidebar' => $embed?->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embed?->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embed?->get('enable_theme_switcher'),
            'defaultTheme' => $embed?->get('default_theme'),
            'enableChatExpand' => $embed?->get('enable_chat_expand'),
            'enableLanguageSwitcher' => (bool) ($embed?->get('show_language_switcher') ?? true),
            // The languages the owner enabled restrict the header switcher;
            // none means every platform language.
            'enabledLanguages' => $agent instanceof Agent ? array_keys($agent->enabledLanguages()) : [],
            'locale' => $embed?->get('language') ?: 'auto',
        ];
    }
}
