<?php

namespace App\Http\Chat;

use Domain\Agent\Models\Agent;
use Domain\Llm\Services\DocumentLlm;

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
    public static function for(?Agent $agent, DocumentLlm $documentLlm): array
    {
        $embed = $agent?->settings('embed');
        $appearance = $agent?->settings('appearance');

        return [
            'appearance' => $appearance,
            // Chat uploads are PDFs, so the upload button only shows when the
            // owner allows uploads AND an LLM that can read PDFs is set up
            // (the same rule as the widget settings).
            'allowFileUploads' => $agent instanceof Agent
                && (bool) ($appearance?->get('allow_file_uploads') ?? true)
                && $documentLlm->isAvailable(),
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
