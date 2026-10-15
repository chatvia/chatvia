<script setup lang="ts">
/**
 * One hosted-chat conversation (also the dashboard playground). Loads the
 * transcript, streams replies over SSE and lets the visitor edit, regenerate,
 * retry, approve tool calls and delete messages. The work lives in the
 * composables under `@/composables/chat`; this page wires them to the layout,
 * the transcript list and the composer.
 */
import { submitInlineForm } from '@/api/chat';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import ChatMessageList from '@/components/chat/ChatMessageList.vue';
import PoweredByFooter from '@/components/chat/PoweredByFooter.vue';
import VoiceModeOverlay from '@/components/chat/voice/VoiceModeOverlay.vue';
import { useChatMessages } from '@/composables/chat/useChatMessages';
import { useChatScroll } from '@/composables/chat/useChatScroll';
import { isNewConversationVisit, useChatStreamTurn } from '@/composables/chat/useChatStreamTurn';
import { useChatSurface } from '@/composables/chat/useChatSurface';
import { useChatTurnActions } from '@/composables/chat/useChatTurnActions';
import { useVoiceTranscript } from '@/composables/chat/useVoiceTranscript';
import { deleteMethod as deleteAttachment, store as storeAttachment } from '@/routes/chat/messages/attachments';
import type { ChatAppearance, ChatDefaultTheme } from '@/types/chat';
import { useChatEventBus } from '@/utils/eventBus';
import { ChevronDown } from 'lucide-vue-next';
import { computed, nextTick, onMounted, provide, ref } from 'vue';
import { useI18n } from 'vue-i18n';
// Global transcript and markdown styles; imported last so they keep the
// cascade position the page's own (unscoped) style block used to have.
import '../../../css/chat.css';

interface Props {
    agent_id: number;
    conversation_id: string;
    appearance?: ChatAppearance;
    removeSidebar?: boolean;
    hideSidebarOnPageLoad?: boolean;
    enableThemeSwitcher?: boolean;
    defaultTheme?: ChatDefaultTheme;
    enableChatExpand?: boolean;
    /** On the iframe-embed pages rather than the full hosted chat. */
    embedded?: boolean;
    /** Owner's pinned chat language ('auto' = detect from the visitor). */
    locale?: string;
    /** Whether to show the visitor language switcher in the header. */
    enableLanguageSwitcher?: boolean;
    /** Language codes the owner enabled in Setup → Languages. Empty means all locales. */
    enabledLanguages?: string[];
    /** Whether the composer offers PDF uploads: the owner allows them and the server can read PDFs. */
    allowFileUploads?: boolean;
    /** Whether the composer offers dictation: the owner allows it and the server has speech to text set up. */
    allowVoiceInput?: boolean;
    /** Whether the composer offers voice conversations: the owner turned them on and the server has them set up. */
    voiceConversation?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    appearance: () => ({
        color_theme: 'black',
        welcome_message: null,
        chat_window_title: null,
        avatar: null,
        composer_placeholder: null,
    }),
    removeSidebar: false,
    hideSidebarOnPageLoad: false,
    enableThemeSwitcher: true,
    defaultTheme: 'auto',
    enableChatExpand: true,
    embedded: false,
    locale: 'auto',
    enableLanguageSwitcher: false,
    enabledLanguages: () => [],
    allowFileUploads: false,
    allowVoiceInput: false,
    voiceConversation: false,
});

const { t } = useI18n();
const bus = useChatEventBus();
const { resolvedAppearance, maxFilesPerChat, canSendMessages } = useChatSurface(props);

const messagesContainer = ref<HTMLElement | null>(null);
const scroll = useChatScroll(messagesContainer);
const transcript = useChatMessages(props.conversation_id);
const turn = useChatStreamTurn({ conversationId: props.conversation_id, transcript, scroll });
const actions = useChatTurnActions({
    conversationId: props.conversation_id,
    embedded: props.embedded,
    transcript,
    turn,
    scroll,
});

const { messages } = transcript;
const { showScrollToBottom } = scroll;
const { isStreaming, isMessageStreaming, liveAnnouncement, activeCalls, hasActiveCalls, approvals, stop: stopStreaming } = turn;
const { resolvingApprovalIds, hasPendingApprovals } = approvals;
const loadingMessages = ref(true);
const isVoiceOpen = ref(false);
const voiceTranscript = useVoiceTranscript(transcript, () => void nextTick(() => scroll.scrollToBottom()));

function closeVoice() {
    isVoiceOpen.value = false;
    void voiceTranscript.syncAfterCall();
}
const isChatExpanded = computed(() => bus.isChatExpanded.value);

// InlineFormBlock (deep inside assistant messages) injects this so it stays
// transport-agnostic; the widget provides its own. A 409 (already submitted)
// or 410 (expired) rejects, and the form block handles both.
provide('inlineFormSubmit', submitInlineForm);

const uploadHeaders = computed(() => ({ 'X-AGENT-ID': String(props.agent_id), 'X-CONVERSATION-ID': props.conversation_id }));
const deleteEndpoint = (attachmentId: string) => deleteAttachment.url({ attachment: attachmentId });

/**
 * A conversation created on Home arrives with only the visitor's first
 * message stored; its reply is streamed from here, with the uploaded files.
 */
function streamFirstReply() {
    const first = messages.value[0];
    const assistantIndex = messages.value.length;
    messages.value.push({ role: 'assistant', content: '', id: 0 });

    turn.startTurn({
        message: first.content,
        userIndex: 0,
        assistantIndex,
        attachments: first.metadata?.attachments ?? [],
        isNewConversation: true,
    });
}

onMounted(async () => {
    // A brand-new conversation renders straight away; an existing one shows the loader.
    loadingMessages.value = !isNewConversationVisit();

    try {
        await transcript.load();
        loadingMessages.value = false;

        if (messages.value.length === 1) streamFirstReply();

        void nextTick(() => scroll.scrollToBottom());
        // Sources load after the transcript so they never delay the first paint.
        void transcript.loadSources();
    } catch {
        actions.goToNewChat();
    }
});
</script>

<template>
    <ChatLayout
        :appearance="resolvedAppearance"
        :removeSidebar="removeSidebar"
        :hideSidebarOnPageLoad="hideSidebarOnPageLoad"
        :enableThemeSwitcher="enableThemeSwitcher"
        :defaultTheme="defaultTheme"
        :enableChatExpand="enableChatExpand"
        :enableLanguageSwitcher="enableLanguageSwitcher"
        :enabledLanguages="enabledLanguages"
        :embedded="embedded"
    >
        <template #content>
            <!-- Screen-reader status channel: narrates streaming, tool and
                 completion state without announcing every token. -->
            <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ liveAnnouncement }}</div>
            <div class="relative flex h-full flex-col overflow-hidden">
                <div
                    id="messages-container"
                    ref="messagesContainer"
                    :class="['flex-1 overflow-x-hidden overflow-y-auto', { 'flex flex-col justify-between': messages.length === 0 }]"
                >
                    <ChatMessageList
                        :messages="messages"
                        :loading="loadingMessages"
                        :expanded="isChatExpanded"
                        :conversation-id="conversation_id"
                        :avatar="appearance?.avatar"
                        :is-message-streaming="isMessageStreaming"
                        :has-active-calls="hasActiveCalls"
                        :active-calls="activeCalls"
                        :sources-for="transcript.sourcesFor"
                        :artifacts-for="transcript.artifactsFor"
                        :approvals-for="approvals.approvalsFor"
                        :resolving-approval-ids="resolvingApprovalIds"
                        @message-updated="actions.editInPlace"
                        @message-edited="actions.editAndRegenerate"
                        @message-deleted="actions.deleteMessage"
                        @attachment-deleted="actions.deleteAttachment"
                        @regenerate="actions.regenerate"
                        @retry="actions.retry"
                        @approve-tool="approvals.approve"
                        @deny-tool="approvals.deny"
                    />

                    <!-- Breathing room under the last message -->
                    <div class="pb-6"></div>
                </div>

                <div class="pointer-events-none absolute start-0 end-0 bottom-36 z-10 flex justify-center">
                    <Transition name="scale-fade">
                        <button
                            v-if="showScrollToBottom && messages.length > 0"
                            class="bg-card text-foreground hover:bg-muted ring-border pointer-events-auto flex h-9 w-9 cursor-pointer items-center justify-center rounded-full shadow-lg ring-1 transition-colors"
                            :aria-label="t('chat.scroll_to_bottom')"
                            @click="scroll.scrollToBottom(true)"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </button>
                    </Transition>
                </div>

                <!-- Sticky composer area -->
                <div class="bg-background shrink-0 px-4 pt-4 pb-6 sm:px-6">
                    <div class="mx-auto transition-all duration-300" :class="[isChatExpanded ? 'max-w-5xl' : 'max-w-3xl']">
                        <ChatComposer
                            :isThinking="isStreaming"
                            :placeholder="resolvedAppearance.composer_placeholder ?? undefined"
                            :allow-file-uploads="allowFileUploads"
                            :allow-voice-input="allowVoiceInput"
                            :allow-voice-conversation="voiceConversation"
                            :max-files="maxFilesPerChat"
                            :disabled="!canSendMessages || hasPendingApprovals"
                            :upload-endpoint="storeAttachment.url()"
                            :upload-headers="uploadHeaders"
                            :delete-endpoint="deleteEndpoint"
                            @messageAdded="actions.send"
                            @stopStreaming="stopStreaming"
                            @startVoice="isVoiceOpen = true"
                        />

                        <PoweredByFooter />
                    </div>
                </div>

                <VoiceModeOverlay v-if="isVoiceOpen" :conversation-id="conversation_id" @turn="voiceTranscript.onTurn" @close="closeVoice" />
            </div>
        </template>
    </ChatLayout>
</template>
