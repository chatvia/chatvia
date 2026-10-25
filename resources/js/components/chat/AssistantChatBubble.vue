<script setup lang="ts">
/**
 * One assistant reply in the hosted chat: the model's thinking (when the
 * agent reasons, live only), the rendered answer, a "still working"
 * indicator (dots or the tool-call stack), any tool calls awaiting approval,
 * and the action row (copy, feedback, delete, regenerate, sources). After a
 * thumbs-down, an optional note on what was wrong.
 */
import { deleteMessage as deleteMessageRequest, sendMessageFeedback, type MessageFeedbackValue } from '@/api/chat';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { ActiveToolCall, Message, MessageSource } from '@/types/chat';
import type { ToolApprovalCall, ToolArtifact } from '@/types/stream';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, RefreshCw, ThumbsDown, ThumbsUp, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { citedSources } from '@/lib/citations';
import AssistantMessageBody from './AssistantMessageBody.vue';
import CitedSourcesList from './CitedSourcesList.vue';
import ReasoningBlock from './ReasoningBlock.vue';
import SourcesDialog from './SourcesDialog.vue';
import ToolApprovalCard from './ToolApprovalCard.vue';
import ToolCallIndicator from './ToolCallIndicator.vue';
import WaitingIndicator from './WaitingIndicator.vue';

type MessageId = string | number;
type Feedback = 'thumbs-up' | 'thumbs-down';

const props = defineProps<{
    message: Message;
    userMessageId?: MessageId;
    isThinking?: boolean;
    isStreaming?: boolean;
    /** The last row of the transcript: its actions stay visible, older replies show them on hover. */
    isLatest?: boolean;
    isCallingExternal?: boolean;
    activeCalls?: ActiveToolCall[];
    hideFeedback?: boolean;
    sources?: MessageSource[];
    /** The agent cites its sources: `[n]` markers become chips, with a numbered source list. */
    citeSources?: boolean;
    /**
     * Structured tool-call artifacts of this turn (ECharts options, forms),
     * from the SSE `tool` end-phase payload. Rendered above the markdown,
     * which usually describes them.
     */
    artifacts?: ToolArtifact[];
    conversationId?: string;
    avatar?: string | null;
    isRTL?: boolean;
    /** Tool calls in this turn paused awaiting the user's approve/deny. */
    pendingApprovals?: ToolApprovalCall[];
    /** approval_ids currently being resolved (buttons show a spinner + disable). */
    resolvingApprovalIds?: string[];
}>();

const emit = defineEmits<{
    messageDeleted: [id: MessageId];
    feedbackProvided: [id: MessageId, type: Feedback];
    regenerate: [userMessageId: MessageId, assistantMessageId: MessageId, onDone: () => void];
    approveTool: [call: ToolApprovalCall];
    denyTool: [call: ToolApprovalCall];
}>();

const { t } = useI18n();
const { copy, copied: isCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });

const WIRE_FEEDBACK: Record<Feedback, MessageFeedbackValue> = { 'thumbs-up': 'positive', 'thumbs-down': 'negative' };

const feedback = ref<Feedback | null>(null);
const isRegenerating = ref(false);

// The sources the answer cites, numbered as its chips are.
const cited = computed(() => citedSources(props.message.content ?? '', props.sources ?? []));
const showsCitations = computed(() => !!props.citeSources && cited.value.ordered.some((entry) => entry.number !== null));

/** The longest note the server keeps (RecordMessageFeedbackAction::COMMENT_MAX_LENGTH). */
const COMMENT_MAX_LENGTH = 1000;
// The note box opens after a fresh thumbs-down and closes once sent or skipped.
const isCommentOpen = ref(false);
const comment = ref('');
const isSendingComment = ref(false);
const isCommentSent = ref(false);

const hasPendingApprovals = computed(() => (props.pendingApprovals?.length ?? 0) > 0);
const activeCallList = computed(() => props.activeCalls ?? []);

// On devices with a mouse, older replies keep their action row out of the way
// until hovered or focused. A thumb already given stays in sight.
const actionsAlwaysVisible = computed(() => props.isLatest || feedback.value !== null);

/** Server ids only: the stub id 0 (and a missing id) cannot be acted on yet. */
const persistedId = computed<MessageId | null>(() => (props.message.id === undefined || props.message.id === null ? null : props.message.id));

watch(
    () => props.message.feedback,
    (value) => {
        feedback.value = value === 'positive' ? 'thumbs-up' : value === 'negative' ? 'thumbs-down' : null;
    },
    { immediate: true },
);

function regenerate() {
    if (persistedId.value === null || props.userMessageId === undefined || !props.conversationId) return;

    isRegenerating.value = true;
    emit('regenerate', props.userMessageId, persistedId.value, () => {
        isRegenerating.value = false;
    });
}

/** Clicking the active thumb again clears it. A failed save resets the thumbs. */
async function provideFeedback(type: Feedback) {
    const next = feedback.value === type ? null : type;
    feedback.value = next;

    if (persistedId.value === null || !props.conversationId) return;

    isCommentOpen.value = false;
    isCommentSent.value = false;

    try {
        await sendMessageFeedback(props.conversationId, persistedId.value, next ? WIRE_FEEDBACK[next] : null);
        if (next !== null) emit('feedbackProvided', persistedId.value, next);
        if (next === 'thumbs-down') {
            comment.value = '';
            isCommentOpen.value = true;
        }
    } catch {
        feedback.value = null;
    }
}

/** Sends the note with the thumbs-down it belongs to. A failed send keeps the box open with the text. */
async function sendComment() {
    const note = comment.value.trim();
    if (note === '' || persistedId.value === null || !props.conversationId) return;

    isSendingComment.value = true;
    try {
        await sendMessageFeedback(props.conversationId, persistedId.value, 'negative', note);
        isCommentOpen.value = false;
        isCommentSent.value = true;
    } catch {
        // The box stays open, so the visitor can try again.
    } finally {
        isSendingComment.value = false;
    }
}

async function deleteMessage() {
    if (persistedId.value === null || !props.conversationId) return;

    try {
        await deleteMessageRequest(props.conversationId, persistedId.value);
        emit('messageDeleted', persistedId.value);
    } catch {
        // The message stays in place, which tells the visitor the delete did not happen.
    }
}
</script>

<template>
    <div class="group flex w-full items-start">
        <div class="max-w-full min-w-0 flex-1">
            <ReasoningBlock
                v-if="message.reasoning"
                :text="message.reasoning"
                :thinking="(isStreaming ?? false) && !message.content"
                :isRTL="isRTL ?? false"
            />
            <AssistantMessageBody
                :content="message.content ?? ''"
                :artifacts="props.artifacts"
                :is-streaming="isStreaming ?? false"
                :isRTL="isRTL ?? false"
                :message-id="message.id ? String(message.id) : undefined"
                :sources="sources ?? []"
                :cite="!!citeSources"
            />
            <CitedSourcesList v-if="showsCitations && !isStreaming" :cited="cited" />
            <!-- Empty body: dots while the model thinks, or the branded tool-call
                 stack while it calls an MCP server or webhook. Hidden while an
                 approval card is pending, since the card is the content then,
                 and while its thinking streams, which shows it is working. -->
            <div v-if="!message.content && !hasPendingApprovals && (isCallingExternal || !message.reasoning)" class="flex items-center gap-2 py-1">
                <ToolCallIndicator v-if="isCallingExternal" :calls="activeCallList" label-class="text-sm" />
                <WaitingIndicator v-else />
            </div>
            <!-- The model streamed some text before calling a tool: show the
                 indicator under the text instead of next to the dots. -->
            <div v-if="message.content && isCallingExternal" class="mt-2 flex items-center gap-2 py-1 text-sm">
                <ToolCallIndicator :calls="activeCallList" />
            </div>

            <!-- Human-in-the-loop: gated tool calls awaiting the user's go-ahead. -->
            <div v-if="hasPendingApprovals" class="mt-2 space-y-2">
                <ToolApprovalCard
                    v-for="call in pendingApprovals"
                    :key="call.tool_call_id"
                    :call="call"
                    :resolving="(resolvingApprovalIds ?? []).includes(call.approval_id)"
                    @approve="(c) => emit('approveTool', c)"
                    @deny="(c) => emit('denyTool', c)"
                />
            </div>

            <!-- Hidden while an approval is pending: feedback on a turn whose
                 tool call has not run yet would be misleading. -->
            <div
                v-if="message.content && !hideFeedback && !hasPendingApprovals"
                class="mt-2 flex flex-wrap items-center justify-between gap-2"
                :class="{
                    'transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100 [@media(hover:hover)]:opacity-0':
                        !actionsAlwaysVisible,
                }"
            >
                <div class="flex items-center gap-0.5">
                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :aria-label="t('messages.copy_message')"
                        @click="copy(message.content)"
                    >
                        <Check v-if="isCopied" class="h-3.5 w-3.5 text-green-500" />
                        <Copy v-else class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :class="{ '!bg-green-500/10 !text-green-600': feedback === 'thumbs-up' }"
                        :aria-pressed="feedback === 'thumbs-up'"
                        :aria-label="t('messages.feedback_helpful')"
                        @click="provideFeedback('thumbs-up')"
                    >
                        <ThumbsUp class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :class="{ '!bg-red-500/10 !text-red-600': feedback === 'thumbs-down' }"
                        :aria-pressed="feedback === 'thumbs-down'"
                        :aria-label="t('messages.feedback_not_helpful')"
                        @click="provideFeedback('thumbs-down')"
                    >
                        <ThumbsDown class="h-3.5 w-3.5" />
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-muted-foreground h-7 w-7 hover:bg-red-500/10 hover:text-red-600"
                                :aria-label="t('common.delete')"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent class="rounded-2xl">
                            <AlertDialogHeader>
                                <AlertDialogTitle>{{ t('messages.delete_assistant_message_title') }}</AlertDialogTitle>
                                <AlertDialogDescription>{{ t('messages.delete_assistant_message_description') }}</AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel class="rounded-lg">{{ t('common.cancel') }}</AlertDialogCancel>
                                <AlertDialogAction @click="deleteMessage" class="rounded-lg bg-red-600 hover:bg-red-700">{{
                                    t('common.delete')
                                }}</AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    <Button
                        v-if="userMessageId !== undefined"
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :disabled="isRegenerating"
                        @click="regenerate"
                        :title="t('messages.regenerate_tooltip')"
                        :aria-label="t('messages.regenerate_tooltip')"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRegenerating }" />
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <SourcesDialog v-if="sources && sources.length > 0 && !showsCitations" :sources="sources" />
                </div>
            </div>

            <form
                v-if="isCommentOpen && feedback === 'thumbs-down'"
                class="bg-muted/40 mt-2 flex max-w-md flex-col gap-2 rounded-xl border p-3"
                @submit.prevent="sendComment"
            >
                <div class="flex items-center justify-between gap-2">
                    <label :for="`feedback-note-${persistedId}`" class="text-sm font-medium">{{ t('messages.feedback_comment_label') }}</label>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground h-6 w-6"
                        :aria-label="t('messages.feedback_comment_skip')"
                        @click="isCommentOpen = false"
                    >
                        <X class="h-3.5 w-3.5" />
                    </Button>
                </div>
                <Textarea
                    :id="`feedback-note-${persistedId}`"
                    v-model="comment"
                    rows="2"
                    dir="auto"
                    :maxlength="COMMENT_MAX_LENGTH"
                    :placeholder="t('messages.feedback_comment_placeholder')"
                    class="resize-none text-sm"
                />
                <div class="flex justify-end">
                    <Button type="submit" size="sm" class="h-7 rounded-lg px-3 text-xs" :disabled="comment.trim() === '' || isSendingComment">
                        {{ t('messages.feedback_comment_send') }}
                    </Button>
                </div>
            </form>
            <p v-else-if="isCommentSent && feedback === 'thumbs-down'" class="text-muted-foreground mt-2 text-xs" role="status">
                {{ t('messages.feedback_comment_thanks') }}
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Prose customizations */
:deep(.prose) {
    font-size: 1rem;
    line-height: 1.75rem;
    color: var(--foreground);
}

:deep(.prose p) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

:deep(.prose p:first-child) {
    margin-top: 0;
}

:deep(.prose p:last-child) {
    margin-bottom: 0;
}

:deep(.prose pre) {
    background-color: var(--muted);
    border: 1px solid var(--border);
    margin: 0.75rem 0;
    position: relative;
    border-radius: 0.5rem;
}

:deep(.prose code) {
    font-size: 0.8rem;
}

:deep(.prose :not(pre) > code) {
    background-color: var(--muted);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
}

/* Code copy button */
:deep(.code-copy-button) {
    position: absolute;
    top: 0.5rem;
    inset-inline-end: 0.5rem;
    padding: 0.25rem;
    background-color: var(--background);
    border: 1px solid var(--border);
    border-radius: 0.25rem;
    color: var(--muted-foreground);
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(pre:hover .code-copy-button) {
    opacity: 1;
}

:deep(.code-copy-button:hover) {
    background-color: var(--accent);
    color: var(--accent-foreground);
}

:deep(.code-copy-button.copied) {
    background-color: var(--success);
    color: white;
    border-color: var(--success);
}

/* Alert styling */
:deep(.markdown-alert) {
    padding: 0.75rem 1rem;
    margin: 0.75rem 0;
    border-inline-start-width: 4px;
    border-inline-start-style: solid;
    border-radius: 0.375rem;
}

:deep(.markdown-alert-note) {
    border-inline-start-color: var(--primary);
    background-color: color-mix(in srgb, var(--primary) 10%, transparent);
}

:deep(.markdown-alert-tip) {
    border-inline-start-color: var(--success);
    background-color: color-mix(in srgb, var(--success) 10%, transparent);
}

:deep(.markdown-alert-important) {
    border-inline-start-color: var(--warning);
    background-color: color-mix(in srgb, var(--warning) 10%, transparent);
}

:deep(.markdown-alert-warning) {
    border-inline-start-color: var(--warning);
    background-color: color-mix(in srgb, var(--warning) 10%, transparent);
}

:deep(.markdown-alert-caution) {
    border-inline-start-color: var(--destructive);
    background-color: color-mix(in srgb, var(--destructive) 10%, transparent);
}

:deep(.markdown-alert-title) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

/* Tables */
:deep(.prose table) {
    border-collapse: collapse;
    margin: 1rem 0;
    width: 100%;
}

:deep(.prose thead) {
    border-bottom: 2px solid var(--border);
}

:deep(.prose th) {
    font-weight: 600;
    padding: 0.75rem;
    text-align: start;
}

:deep(.prose td) {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
}

:deep(.prose tbody tr:last-child td) {
    border-bottom: none;
}

/* Lists */
:deep(.prose ul) {
    list-style-type: disc;
    padding-inline-start: 1.5rem;
}

:deep(.prose ol) {
    list-style-type: decimal;
    padding-inline-start: 1.5rem;
}

:deep(.prose li) {
    margin: 0.25rem 0;
}

/* Blockquotes */
:deep(.prose blockquote) {
    border-inline-start: 3px solid color-mix(in srgb, var(--muted-foreground) 30%, transparent);
    padding-inline-start: 1rem;
    margin: 1rem 0;
    font-style: italic;
    color: var(--muted-foreground);
}

/* Links */
:deep(.prose a) {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
}

:deep(.prose a:hover) {
    text-decoration: none;
}

/* Headings */
:deep(.prose h1),
:deep(.prose h2),
:deep(.prose h3),
:deep(.prose h4),
:deep(.prose h5),
:deep(.prose h6) {
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

:deep(.prose h1) {
    font-size: 1.5rem;
}
:deep(.prose h2) {
    font-size: 1.25rem;
}
:deep(.prose h3) {
    font-size: 1.125rem;
}
:deep(.prose h4) {
    font-size: 1rem;
}

/* Images */
:deep(.prose img) {
    border-radius: 0.5rem;
    margin: 1rem 0;
    max-width: 100%;
    height: auto;
}

/* Horizontal rule */
:deep(.prose hr) {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
}

/* Right-to-left replies (Arabic, Persian, …). The rules above use logical
   properties, so flipping `direction` also moves list indents, blockquote
   borders and table alignment to the right side. */
:deep(.prose[data-rtl='true'] p),
:deep(.prose[data-rtl='true'] h1),
:deep(.prose[data-rtl='true'] h2),
:deep(.prose[data-rtl='true'] h3),
:deep(.prose[data-rtl='true'] h4),
:deep(.prose[data-rtl='true'] h5),
:deep(.prose[data-rtl='true'] h6),
:deep(.prose[data-rtl='true'] ul),
:deep(.prose[data-rtl='true'] ol),
:deep(.prose[data-rtl='true'] blockquote),
:deep(.prose[data-rtl='true'] th),
:deep(.prose[data-rtl='true'] td),
:deep(.prose[data-rtl='true'] img) {
    direction: rtl;
    text-align: start;
}

/* Maths and code stay left-to-right whatever the language. */
:deep(.prose[data-rtl='true'] .katex-display),
:deep(.prose[data-rtl='true'] .katex) {
    direction: ltr !important;
    text-align: start !important;
}

:deep(.prose[data-rtl='true'] pre),
:deep(.prose[data-rtl='true'] code) {
    direction: ltr;
    text-align: start;
}
</style>
