<?php

namespace App\Http\Controllers\Chat\Embed;

use Inertia\Inertia;

class ChatEmbedController
{
    public function __invoke(string $conversationId)
    {
        $agent = current_agent();

        $embedSettings = $agent->settings('embed');

        return Inertia::render('chat/Chat', [
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'appearance' => $agent?->settings('appearance'),
            'removeSidebar' => $embedSettings->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embedSettings->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embedSettings->get('enable_theme_switcher'),
            'defaultTheme' => $embedSettings->get('default_theme'),
            'enableChatExpand' => $embedSettings->get('enable_chat_expand'),
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
