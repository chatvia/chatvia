<?php

namespace App\Http\Controllers\Chat\Home;

use Illuminate\Http\Request;
use Inertia\Inertia;

final class ChatController
{
    public function __invoke(Request $request, string $conversationId)
    {
        $agent = current_agent();
        $agent?->loadSettingsGroups(['appearance', 'embed']);
        $agent?->loadMissing('team.owner');
        $embed = $agent?->settings('embed');

        // Attachments live on each message now (rendered under the user
        // bubble by UserChatBubble), so the page render no longer needs a
        // conversation-level chip strip — ConversationMessageController
        // hydrates per-message `attachments` for the messages fetch.

        return Inertia::render('chat/Chat', [
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'appearance' => $agent?->settings('appearance'),
            'removeSidebar' => $embed?->get('remove_sidebar'),
            'hideSidebarOnPageLoad' => $embed?->get('hide_sidebar_on_page_load'),
            'enableThemeSwitcher' => $embed?->get('enable_theme_switcher'),
            'defaultTheme' => $embed?->get('default_theme'),
            'enableChatExpand' => $embed?->get('enable_chat_expand'),
            'locale' => $embed?->get('language') ?: 'auto',
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
