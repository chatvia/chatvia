<script setup lang="ts">
/**
 * One hosted-chat conversation (also the dashboard playground). Loads the
 * transcript, streams replies over SSE and lets the visitor edit, regenerate,
 * retry, approve tool calls and delete messages. The work lives in the
 * composables under `@/composables/chat`; this page wires them to the layout,
 * the transcript list and the composer.
 */
import { bookCalendarSlot, listCalendarSlots, submitInlineForm } from '@/api/chat';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import ChatMessageList from '@/components/chat/ChatMessageList.vue';
import PoweredByFooter from '@/components/chat/PoweredByFooter.vue';
import VoiceModeOverlay from '@/components/chat/voice/VoiceModeOverlay.vue';
import { useChatMessages } from '@/composables/chat/useChatMessages';
import { useGeneratedMediaUpdates } from '@/composables/chat/useGeneratedMediaUpdates';
import { provideChatModelChoice, type OfferedModel } from '@/composables/chat/useChatModelChoice';
import { useChatScroll } from '@/composables/chat/useChatScroll';
import { isNewConversationVisit, useChatStreamTurn } from '@/composables/chat/useChatStreamTurn';
import { useChatSurface } from '@/composables/chat/useChatSurface';
import { useChatTurnActions } from '@/composables/chat/useChatTurnActions';
import { useVoiceTranscript } from '@/composables/chat/useVoiceTranscript';
import { deleteMethod as deleteAttachment, store as storeAttachment } from '@/routes/chat/messages/attachments';
import type { ChatAppearance, ChatDefaultTheme } from '@/types/chat';
import { useChatEventBus } from '@/utils/eventBus';
import { ChevronDown } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, provide, ref } from 'vue';
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
    /** The MIME types the file picker offers: what the server's model for chat files reads. */
    uploadAccept?: string;
    /** Whether the composer offers dictation: the owner allows it and the server has speech to text set up. */
    allowVoiceInput?: boolean;
    /** Whether the composer offers voice conversations: the owner turned them on and the server has them set up. */
    voiceConversation?: boolean;
    /** Whether replies get a speaker button: the owner turned it on and the server has text to speech set up. */
    readAloud?: boolean;
    /** Whether a thumbs-down opens the optional "What was wrong?" note (the owner can turn it off). */
    feedbackComment?: boolean;
    /** The models the chatter may pick from; empty hides the picker. */
    models?: OfferedModel[];
}

const props = withDefaults(defineProps<Props>(), {
    appearance: () => ({
        color_theme: 'black',
        welcome_message: null,
        chat_window_title: null,
        avatar: null,
        composer_placeholder: null,
        notice: null,
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
    uploadAccept: 'application/pdf',
    allowVoiceInput: false,
    voiceConversation: false,
    readAloud: false,
    feedbackComment: true,
    models: () => [],
});

const { t } = useI18n();
const bus = useChatEventBus();
const { resolvedAppearance, maxFilesPerChat, messageMaxLength, canSendMessages } = useChatSurface(props);

const messagesContainer = ref<HTMLElement | null>(null);
const transcriptContent = ref<HTMLElement | null>(null);
const scroll = useChatScroll(messagesContainer, transcriptContent);
const transcript = useChatMessages(props.conversation_id);
useGeneratedMediaUpdates(props.conversation_id, transcript);
const modelChoice = provideChatModelChoice(props.agent_id, () => props.models);
const turn = useChatStreamTurn({ conversationId: props.conversation_id, transcript, scroll, modelId: () => modelChoice.modelId.value });
const actions = useChatTurnActions({
    conversationId: props.conversation_id,
    pickModel: modelChoice.pick,
    embedded: props.embedded,
    transcript,
    turn,
    scroll,
});

const { messages } = transcript;
const { showScrollToBottom, spacerHeight } = scroll;
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
// The booking calendar's transport, injected the same way.
provide('inlineCalendar', { slots: listCalendarSlots, book: bookCalendarSlot });

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
    void scroll.pinToTop(0);

    turn.startTurn({
        message: first.content,
        userIndex: 0,
        assistantIndex,
        attachments: first.metadata?.attachments ?? [],
        isNewConversation: true,
    });
}

const FIRST_REPLY_CHECK_EVERY_MS = 2000;
const FIRST_REPLY_WAIT_MS = 60_000;
let unmounted = false;
onUnmounted(() => (unmounted = true));

/**
 * A reload while the first reply was streaming lands here with only the
 * visitor's message: the server is still finishing that reply (or has just
 * saved it), so the page waits for it instead of asking for a second answer,
 * which would run its tools (a generated image) again. Only a reply that
 * never comes is asked for again.
 */
async function waitForFirstReply() {
    const until = Date.now() + FIRST_REPLY_WAIT_MS;

    while (!unmounted && Date.now() < until) {
        messages.value.push({ role: 'assistant', content: '', id: 0 });
        await new Promise((resolve) => setTimeout(resolve, FIRST_REPLY_CHECK_EVERY_MS));

        try {
            await transcript.load();
        } catch {
            messages.value.splice(1);
            continue;
        }

        if (messages.value.length > 1) {
            void nextTick(() => scroll.scrollToBottom());
            void transcript.loadSources();
            return;
        }
    }

    if (unmounted) return;
    messages.value.splice(1);
    streamFirstReply();
}

onMounted(async () => {
    // A brand-new conversation renders straight away; an existing one shows the loader.
    const isNewVisit = isNewConversationVisit();
    loadingMessages.value = !isNewVisit;

    try {
        await transcript.load();
        loadingMessages.value = false;

        if (messages.value.length === 1) {
            // Home sends a new conversation here once, flagged: only that
            // visit asks for the first reply.
            if (isNewVisit) streamFirstReply();
            else void waitForFirstReply();
        } else {
            void nextTick(() => scroll.scrollToBottom());
        }
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
                <div id="messages-container" ref="messagesContainer" class="flex-1 overflow-x-hidden overflow-y-auto">
                    <div ref="transcriptContent" :class="{ 'h-full': loadingMessages }">
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
                            :cite-sources="resolvedAppearance.sources_display === 'citations'"
                            :read-aloud="readAloud"
                            :feedback-comment="feedbackComment"
                            :artifacts-for="transcript.artifactsFor"
                            :approvals-for="approvals.approvalsFor"
                            :resolving-approval-ids="resolvingApprovalIds"
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

                    <!-- Room under the latest turn, so a sent prompt can move to the top of the view. -->
                    <div aria-hidden="true" :style="{ height: `${spacerHeight}px` }"></div>
                </div>

                <!-- Sticky composer area. The fade above it lets the transcript
                     run out softly instead of being cut off at its edge. -->
                <div class="bg-background relative shrink-0 px-4 pt-2 pb-6 sm:px-6">
                    <div class="from-background pointer-events-none absolute start-0 end-0 bottom-full h-8 bg-gradient-to-t to-transparent"></div>

                    <div class="relative mx-auto" :class="[isChatExpanded ? 'max-w-5xl' : 'max-w-3xl']">
                        <div class="pointer-events-none absolute start-0 end-0 bottom-full z-10 mb-4 flex justify-center">
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

                        <ChatComposer
                            :isThinking="isStreaming"
                            :placeholder="resolvedAppearance.composer_placeholder ?? undefined"
                            :allow-file-uploads="allowFileUploads"
                            :upload-accept="uploadAccept"
                            :allow-voice-input="allowVoiceInput"
                            :allow-voice-conversation="voiceConversation"
                            :max-files="maxFilesPerChat"
                            :max-length="messageMaxLength"
                            :disabled="!canSendMessages || hasPendingApprovals"
                            :upload-endpoint="storeAttachment.url()"
                            :upload-headers="uploadHeaders"
                            :delete-endpoint="deleteEndpoint"
                            @messageAdded="actions.send"
                            @stopStreaming="stopStreaming"
                            @startVoice="isVoiceOpen = true"
                        />

                        <p
                            v-if="resolvedAppearance.notice"
                            dir="auto"
                            class="text-muted-foreground/70 mx-auto mt-2 max-w-2xl px-4 text-center text-[11px] leading-snug"
                        >
                            {{ resolvedAppearance.notice }}
                        </p>

                        <PoweredByFooter />
                    </div>
                </div>

                <VoiceModeOverlay v-if="isVoiceOpen" :conversation-id="conversation_id" @turn="voiceTranscript.onTurn" @close="closeVoice" />
            </div>
        </template>
    </ChatLayout>
</template>
