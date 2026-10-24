<?php

namespace App\Http\Chat;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Voice\VoiceAvailability;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Services\ChatFileModel;

/**
 * The page props every hosted-chat page shares (the standalone chat and the
 * iframe embed): the appearance with its per-language texts — the page picks
 * the visitor's language itself, so the header switcher can change it live —
 * the owner's embed choices, and which composer buttons to show.
 */
final readonly class HostedChatPageProps
{
    public function __construct(
        private ChatFileModel $fileModel,
        private VoiceAvailability $voice,
        private Transcriber $transcriber,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(?Agent $agent): array
    {
        $embed = $agent?->settings('embed');
        $appearance = $agent?->settings('appearance');

        return [
            'appearance' => $appearance,
            // The upload button only shows when the owner allows uploads AND
            // a model that reads files is set up; it offers the types that
            // model reads (the same rule as the widget settings).
            'allowFileUploads' => $agent instanceof Agent
                && (bool) ($appearance?->get('allow_file_uploads') ?? true)
                && $this->fileModel->isAvailable(),
            'uploadAccept' => implode(',', $this->fileModel->acceptedMimeTypes()),
            // The mic (dictation) records, then the server writes it down, so
            // it only shows when the owner allows it AND a speech-to-text
            // service is set up (the same rule as the widget settings).
            'allowVoiceInput' => $agent instanceof Agent
                && (bool) ($appearance?->get('allow_voice_input') ?? true)
                && $this->transcriber->isEnabled(),
            // The voice-conversation button: on for the agent and set up on
            // this install (the same rule as the widget settings).
            'voiceConversation' => $agent instanceof Agent && $this->voice->isOnFor($agent),
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
