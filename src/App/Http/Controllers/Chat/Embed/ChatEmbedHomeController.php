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
            'appearance' => current_agent()?->settings('appearance'),
            'removeSidebar' => $embedSettings->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embedSettings->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embedSettings->get('enable_theme_switcher'),
            'defaultTheme' => $embedSettings->get('default_theme'),
            'enableChatExpand' => $embedSettings->get('enable_chat_expand'),
            'locale' => $embedSettings->get('language') ?: 'auto',
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
