<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatHomeController
{
    public function __invoke(Request $request, TurnState $turnState)
    {
        $agent = current_agent();
        $agent?->loadSettingsGroups(['appearance', 'embed']);
        $embed = $agent?->settings('embed');

        return Inertia::render('chat/Home', [
            'appearance' => $agent?->settings('appearance'),
            'agent_id' => $agent?->id,
            'removeSidebar' => $embed?->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embed?->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embed?->get('enable_theme_switcher'),
            'defaultTheme' => $embed?->get('default_theme'),
            'enableChatExpand' => $embed?->get('enable_chat_expand'),
            'locale' => $embed?->get('language') ?: 'auto',
        ]);
    }
}
