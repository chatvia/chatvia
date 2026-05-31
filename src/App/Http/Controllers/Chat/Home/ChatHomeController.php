<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatHomeController
{
    public function __invoke(Request $request, ChatSession $chatSession)
    {
        $agent = current_agent();
        $agent?->loadSettingsGroups(['appearance', 'embed']);
        $embed = $agent?->settings('embed');

        return Inertia::render('chat/Home', [
            'appearance' => $agent?->settings('appearance'),
            'removeSidebar' => $embed?->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embed?->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embed?->get('enable_theme_switcher'),
            'defaultTheme' => $embed?->get('default_theme'),
            'enableChatExpand' => $embed?->get('enable_chat_expand'),
            'locale' => $embed?->get('language') ?: 'auto',
        ]);
    }
}
