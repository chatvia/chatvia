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

        // The appearance payload carries the per-language translation maps; the
        // hosted chat resolves the visitor's language client-side so the header
        // language switcher can re-localize the title/welcome/placeholder live.

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
            'enableLanguageSwitcher' => (bool) ($embed?->get('show_language_switcher') ?? true),
            // Codes the owner enabled in Setup → Languages — restricts the
            // header switcher. Empty ⇒ all platform languages.
            'enabledLanguages' => $agent ? array_keys($agent->enabledLanguages()) : [],
            'locale' => $embed?->get('language') ?: 'auto',
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
