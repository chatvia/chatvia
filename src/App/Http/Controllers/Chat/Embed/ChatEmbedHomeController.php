<?php

namespace App\Http\Controllers\Chat\Embed;

use Inertia\Inertia;

class ChatEmbedHomeController
{
    public function __invoke()
    {
        $agent = current_agent();

        $embedSettings = $agent->settings('embed');

        return Inertia::render('chat/Home', [
            // Raw appearance incl. the per-language maps; the page resolves the
            // visitor's language client-side (header language switcher).
            'appearance' => $agent->settings('appearance'),
            'removeSidebar' => $embedSettings->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embedSettings->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embedSettings->get('enable_theme_switcher'),
            'defaultTheme' => $embedSettings->get('default_theme'),
            'enableChatExpand' => $embedSettings->get('enable_chat_expand'),
            'enableLanguageSwitcher' => (bool) ($embedSettings->get('show_language_switcher') ?? true),
            // Codes the owner enabled in Setup → Languages — restricts the
            // header switcher. Empty ⇒ all platform languages.
            'enabledLanguages' => array_keys($agent->enabledLanguages()),
            'locale' => $embedSettings->get('language') ?: 'auto',
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
