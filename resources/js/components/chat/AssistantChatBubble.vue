<script setup lang="ts">
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
import type { Message } from '@/types/chat';
import type { ToolApprovalCall, ToolArtifact } from '@/types/stream';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, Copy, Plug, RefreshCw, ThumbsDown, ThumbsUp, Trash2, Webhook } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssistantMessageBody from './AssistantMessageBody.vue';
import SourcesDialog from './SourcesDialog.vue';
import ToolApprovalCard from './ToolApprovalCard.vue';
import WaitingIndicator from './WaitingIndicator.vue';

interface ActiveCall {
    tool_call_id: string;
    name: string;
    kind: 'mcp' | 'function';
    label: string;
    icon_url: string | null;
    brand_color: string | null;
    started_at: number;
}

const props = defineProps<{
    message: Message;
    userMessageId?: string | number;
    isThinking?: boolean;
    isStreaming?: boolean;
    isCallingExternal?: boolean;
    activeCalls?: ActiveCall[];
    hideFeedback?: boolean;
    sources?: any[];
    /**
     * Structured tool-call artifacts attached to this assistant turn —
     * currently just ECharts options. Set by Chat.vue from the SSE `tool`
     * end-phase artifact payload. The component renders each one inline
     * above the markdown answer (the LLM's summary text references them).
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
    messageDeleted: [id: string | number];
    feedbackProvided: [id: string | number, type: 'thumbs-up' | 'thumbs-down'];
    regenerate: [userMessageId: string | number, assistantMessageId: string | number, onDone: () => void];
    approveTool: [call: ToolApprovalCall];
    denyTool: [call: ToolApprovalCall];
}>();

const { t } = useI18n();

// The most-recently-started call drives the visible label. `activeCalls`
// arrives sorted oldest-first from Chat.vue, so the last entry is the
// newest. Cap the stacked icons at 4 to keep the row tidy on narrow
// viewports — if more are in flight the count badge picks up the rest.
const STACK_LIMIT = 4;
const stackedCalls = computed(() => (props.activeCalls ?? []).slice(-STACK_LIMIT));
const overflowCount = computed(() => Math.max(0, (props.activeCalls?.length ?? 0) - STACK_LIMIT));
const currentCall = computed<ActiveCall | null>(() => {
    const calls = props.activeCalls;
    return calls && calls.length > 0 ? calls[calls.length - 1] : null;
});
const currentCallLabel = computed(() => {
    const call = currentCall.value;
    if (!call) return t('messages.calling_external');
    return t('messages.calling', { label: call.label });
});

const isRegenerating = ref(false);

function regenerate() {
    if (props.message.id === undefined || props.userMessageId === undefined || !props.conversationId) return;

    isRegenerating.value = true;
    emit('regenerate', props.userMessageId, props.message.id, () => {
        isRegenerating.value = false;
    });
}

const _page = usePage();

const isCopied = ref(false);
const feedback = ref<'thumbs-up' | 'thumbs-down' | null>(null);

async function copyToClipboard() {
    try {
        await navigator.clipboard.writeText(props.message.content);
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy text:', err);
    }
}

async function provideFeedback(type: 'thumbs-up' | 'thumbs-down') {
    const next = feedback.value === type ? null : type;
    feedback.value = next;

    if (props.message.id !== undefined && props.conversationId) {
        try {
            const wireValue = next === 'thumbs-up' ? 'positive' : next === 'thumbs-down' ? 'negative' : null;

            await axios.patch(route('chat.messages.feedback'), {
                conversation_id: props.conversationId,
                message_id: props.message.id,
                feedback: wireValue,
            });

            if (next !== null) {
                emit('feedbackProvided', props.message.id, next);
            }
        } catch (error) {
            console.error('Failed to send feedback:', error);
            feedback.value = null;
        }
    }
}

async function deleteMessage() {
    if (props.message.id !== undefined && props.conversationId) {
        try {
            await axios.delete(route('chat.messages.delete'), {
                data: {
                    conversation_id: props.conversationId,
                    message_id: props.message.id,
                },
            });

            console.log('ChatMessage deleted successfully:', props.message.id);
            emit('messageDeleted', props.message.id);
        } catch (error) {
            console.error('Failed to delete message:', error);
        }
    }
}

watch(
    () => props.message.feedback,
    (value) => {
        if (value === 'positive') feedback.value = 'thumbs-up';
        else if (value === 'negative') feedback.value = 'thumbs-down';
        else feedback.value = null;
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex w-full items-start">
        <div class="max-w-full min-w-0 flex-1">
            <AssistantMessageBody
                :content="message.content ?? ''"
                :artifacts="props.artifacts"
                :is-streaming="isStreaming ?? false"
                :is-rtl="isRTL ?? false"
            />
            <!-- Empty body: show the "still working" indicator. WaitingIndicator
                 if the model is just thinking; branded tool-call icon stack if
                 it's calling an MCP server or webhook function. Suppressed while
                 an approval card is pending — the card is the content then. -->
            <div v-if="!message.content && !(pendingApprovals && pendingApprovals.length)" class="flex items-center gap-2 py-1">
                <template v-if="isCallingExternal">
                    <div class="external-call-stack flex items-center">
                        <div
                            v-for="(call, i) in stackedCalls"
                            :key="call.tool_call_id"
                            class="external-call-icon"
                            :style="{ marginLeft: i === 0 ? '0' : '-6px', zIndex: stackedCalls.length - i }"
                            :title="call.label"
                        >
                            <img v-if="call.icon_url" :src="call.icon_url" :alt="call.label" class="h-full w-full object-contain p-0.5" />
                            <Webhook v-else-if="call.kind === 'function'" class="text-muted-foreground h-full w-full p-1" />
                            <Plug v-else class="text-muted-foreground h-full w-full p-1" />
                        </div>
                        <span v-if="overflowCount > 0" class="text-muted-foreground ms-1 text-xs">+{{ overflowCount }}</span>
                    </div>
                    <span class="external-call-shimmer text-sm italic">{{ currentCallLabel }}</span>
                </template>
                <WaitingIndicator v-else />
            </div>
            <!-- Edge case: the LLM streamed some text before deciding to
                 call a tool. Show the indicator below the content rather
                 than next to the three dots. -->
            <div v-if="message.content && isCallingExternal" class="mt-2 flex items-center gap-2 py-1 text-sm">
                <div class="external-call-stack flex items-center">
                    <div
                        v-for="(call, i) in stackedCalls"
                        :key="call.tool_call_id"
                        class="external-call-icon"
                        :style="{ marginLeft: i === 0 ? '0' : '-6px', zIndex: stackedCalls.length - i }"
                        :title="call.label"
                    >
                        <img v-if="call.icon_url" :src="call.icon_url" :alt="call.label" class="h-full w-full object-contain p-0.5" />
                        <Webhook v-else-if="call.kind === 'function'" class="text-muted-foreground h-full w-full p-1" />
                        <Plug v-else class="text-muted-foreground h-full w-full p-1" />
                    </div>
                    <span v-if="overflowCount > 0" class="text-muted-foreground ms-1 text-xs">+{{ overflowCount }}</span>
                </div>
                <span class="external-call-shimmer italic">{{ currentCallLabel }}</span>
            </div>

            <!-- Human-in-the-loop: gated tool calls awaiting the user's go-ahead. -->
            <div v-if="pendingApprovals && pendingApprovals.length" class="mt-2 space-y-2">
                <ToolApprovalCard
                    v-for="call in pendingApprovals"
                    :key="call.tool_call_id"
                    :call="call"
                    :is-rtl="isRTL ?? false"
                    :resolving="(resolvingApprovalIds ?? []).includes(call.approval_id)"
                    @approve="(c) => emit('approveTool', c)"
                    @deny="(c) => emit('denyTool', c)"
                />
            </div>

            <!-- Suppressed while an approval card is pending — feedback on a turn
                 whose tool call hasn't run yet is misleading. -->
            <div
                v-if="message.content && !hideFeedback && !(pendingApprovals && pendingApprovals.length)"
                class="mt-2 flex flex-wrap items-center justify-between gap-2"
            >
                <div class="flex items-center gap-0.5">
                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        @click="copyToClipboard"
                    >
                        <Check v-if="isCopied" class="h-3.5 w-3.5 text-green-500" />
                        <Copy v-else class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :class="{ '!bg-green-500/10 !text-green-600': feedback === 'thumbs-up' }"
                        @click="provideFeedback('thumbs-up')"
                    >
                        <ThumbsUp class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :class="{ '!bg-red-500/10 !text-red-600': feedback === 'thumbs-down' }"
                        @click="provideFeedback('thumbs-down')"
                    >
                        <ThumbsDown class="h-3.5 w-3.5" />
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button size="icon" variant="ghost" class="text-muted-foreground h-7 w-7 hover:bg-red-500/10 hover:text-red-600">
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

                    <!-- Regenerate button -->
                    <Button
                        v-if="userMessageId !== undefined"
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :disabled="isRegenerating"
                        @click="regenerate"
                        :title="t('messages.regenerate_tooltip')"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRegenerating }" />
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <SourcesDialog v-if="sources && sources.length > 0" :sources="sources" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Prose customizations */
:deep(.prose) {
    font-size: 1rem;
    line-height: 1.75rem;
    color: hsl(var(--foreground));
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
    background-color: hsl(var(--muted));
    border: 1px solid hsl(var(--border));
    margin: 0.75rem 0;
    position: relative;
    border-radius: 0.5rem;
}

:deep(.prose code) {
    font-size: 0.8rem;
}

:deep(.prose :not(pre) > code) {
    background-color: hsl(var(--muted));
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
}

/* Code copy button */
:deep(.code-copy-button) {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.25rem;
    background-color: hsl(var(--background));
    border: 1px solid hsl(var(--border));
    border-radius: 0.25rem;
    color: hsl(var(--muted-foreground));
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
    background-color: hsl(var(--accent));
    color: hsl(var(--accent-foreground));
}

:deep(.code-copy-button.copied) {
    background-color: hsl(var(--success));
    color: white;
    border-color: hsl(var(--success));
}

/* Alert styling */
:deep(.markdown-alert) {
    padding: 0.75rem 1rem;
    margin: 0.75rem 0;
    border-left-width: 4px;
    border-left-style: solid;
    border-radius: 0.375rem;
}

/* RTL alert styling */
:deep([dir='rtl'] .markdown-alert) {
    border-left-width: 0;
    border-right-width: 4px;
    border-right-style: solid;
    padding-right: 1rem;
    padding-left: 1rem;
}

:deep(.markdown-alert-note) {
    border-left-color: hsl(var(--primary));
    background-color: hsl(var(--primary) / 0.1);
}

:deep(.markdown-alert-tip) {
    border-left-color: hsl(var(--success));
    background-color: hsl(var(--success) / 0.1);
}

:deep(.markdown-alert-important) {
    border-left-color: hsl(var(--warning));
    background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-warning) {
    border-left-color: hsl(var(--warning));
    background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-caution) {
    border-left-color: hsl(var(--destructive));
    background-color: hsl(var(--destructive) / 0.1);
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
    border-bottom: 2px solid hsl(var(--border));
}

:deep(.prose th) {
    font-weight: 600;
    padding: 0.75rem;
    text-align: left;
}

:deep(.prose td) {
    padding: 0.75rem;
    border-bottom: 1px solid hsl(var(--border));
}

:deep(.prose tbody tr:last-child td) {
    border-bottom: none;
}

/* Lists */
:deep(.prose ul) {
    list-style-type: disc;
    padding-left: 1.5rem;
}

:deep(.prose ol) {
    list-style-type: decimal;
    padding-left: 1.5rem;
}

:deep(.prose li) {
    margin: 0.25rem 0;
}

/* Blockquotes */
:deep(.prose blockquote) {
    border-left: 3px solid hsl(var(--muted-foreground) / 0.3);
    padding-left: 1rem;
    margin: 1rem 0;
    font-style: italic;
    color: hsl(var(--muted-foreground));
}

/* Links */
:deep(.prose a) {
    color: hsl(var(--primary));
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
    border-top: 1px solid hsl(var(--border));
    margin: 1.5rem 0;
}

/* RTL Support for specific content elements only */
:deep(.prose[data-rtl='true'] p),
:deep(.prose[data-rtl='true'] h1),
:deep(.prose[data-rtl='true'] h2),
:deep(.prose[data-rtl='true'] h3),
:deep(.prose[data-rtl='true'] h4),
:deep(.prose[data-rtl='true'] h5),
:deep(.prose[data-rtl='true'] h6) {
    text-align: right;
    direction: rtl;
}

/* Exclude math content from RTL styling */
:deep(.prose[data-rtl='true'] .katex-display),
:deep(.prose[data-rtl='true'] .katex) {
    direction: ltr !important;
    text-align: left !important;
}

/* Also target any paragraph that contains math */
:deep(.prose[data-rtl='true'] p .katex-display),
:deep(.prose[data-rtl='true'] p .katex) {
    direction: ltr !important;
    text-align: left !important;
}

:deep(.prose[data-rtl='true'] ul),
:deep(.prose[data-rtl='true'] ol) {
    padding-right: 1.5rem;
    padding-left: 0;
    direction: rtl;
}

:deep(.prose[data-rtl='true'] blockquote) {
    border-right: 3px solid hsl(var(--muted-foreground) / 0.3);
    border-left: none;
    padding-right: 1rem;
    padding-left: 0;
    direction: rtl;
}

:deep(.prose[data-rtl='true'] th),
:deep(.prose[data-rtl='true'] td) {
    text-align: right;
    direction: rtl;
}

:deep(.prose[data-rtl='true'] img) {
    direction: rtl;
}

/* Keep code blocks in LTR regardless of language */
:deep(.prose[data-rtl='true'] pre),
:deep(.prose[data-rtl='true'] code) {
    direction: ltr;
    text-align: left;
}

/* ---------------------------------------------------------------
 * Streaming polish — soft "writing itself in" feel.
 *
 * Paired with the client-side pacer in `useChatStream.ts` (which
 * meters delta arrivals at ~80 chars/sec via rAF), the CSS below
 * gives a natural, ChatGPT-like cadence to streamed responses.
 *
 * 1. Trailing fade mask (~3 lines tall)
 *    A multi-stop gradient mask fades the bottom of the content
 *    along an ease-out curve so the freshest characters emerge from
 *    near-transparent rather than snapping in. Tall enough (~90px)
 *    that 2-3 lines participate in the fade at any moment, which is
 *    what makes it read as "writing" rather than "typing".
 *
 * 2. Soft blur on the very last line
 *    A subtle `filter: blur(0.35px)` on the trailing pseudo-edge
 *    softens character outlines just before the cursor — the eye
 *    can't pin down individual letters until they "settle" into
 *    full opacity above the mask.
 *
 * 3. Breathing cursor
 *    Eased opacity + scaleY loop instead of a hard step-end blink,
 *    so the caret doesn't strobe next to the fading text.
 *
 * 4. Graceful exit
 *    All three effects transition out when `is-streaming` flips
 *    off, so the final tokens settle instead of popping.
 * --------------------------------------------------------------- */

.streaming-content {
    /* Default to no fade — these props only animate when the
       component flips into the streaming state below. */
    --stream-fade: 0px;
    --stream-blur: 0;
    transition:
        -webkit-mask-image 320ms ease-out,
        mask-image 320ms ease-out,
        filter 320ms ease-out;
}

.streaming-content.is-streaming {
    /* Tall, multi-stop trailing mask. The intermediate stops
       approximate an ease-out curve so the falloff feels organic
       (a single linear stop reads as "the bottom is grey" rather
       than "text is appearing"). 96px ≈ 3 lines of body text at
       1.75rem line-height. */
    --stream-fade: 96px;
    -webkit-mask-image: linear-gradient(
        to bottom,
        #000 0,
        #000 calc(100% - var(--stream-fade)),
        rgba(0, 0, 0, 0.92) calc(100% - var(--stream-fade) * 0.78),
        rgba(0, 0, 0, 0.72) calc(100% - var(--stream-fade) * 0.55),
        rgba(0, 0, 0, 0.42) calc(100% - var(--stream-fade) * 0.32),
        rgba(0, 0, 0, 0.18) calc(100% - var(--stream-fade) * 0.14),
        transparent 100%
    );
    mask-image: linear-gradient(
        to bottom,
        #000 0,
        #000 calc(100% - var(--stream-fade)),
        rgba(0, 0, 0, 0.92) calc(100% - var(--stream-fade) * 0.78),
        rgba(0, 0, 0, 0.72) calc(100% - var(--stream-fade) * 0.55),
        rgba(0, 0, 0, 0.42) calc(100% - var(--stream-fade) * 0.32),
        rgba(0, 0, 0, 0.18) calc(100% - var(--stream-fade) * 0.14),
        transparent 100%
    );
    /* Sub-pixel blur on the whole element. Above the mask region
       the blur is invisible (mask opacity = 1 reveals the sharp
       glyph), but inside the fade band the half-pixel softening
       gives characters a "wet ink" quality as they materialize. */
    filter: blur(0.35px);
}

/* Eased breathing cursor — replaces the previous step-end blink so it
   reads as a soft pulse next to the fading text rather than a strobe. */
.streaming-content.is-streaming::after {
    content: '▋';
    display: inline-block;
    margin-left: 2px;
    color: hsl(var(--foreground));
    animation: stream-cursor 1.1s ease-in-out infinite;
    transform-origin: bottom;
    /* Cancel out the parent's micro-blur for the caret itself so
       the cursor stays crisp while text behind it softens. */
    filter: blur(0);
}

@keyframes stream-cursor {
    0%,
    100% {
        opacity: 0.85;
        transform: scaleY(1);
    }
    50% {
        opacity: 0.15;
        transform: scaleY(0.94);
    }
}

/* Respect users who prefer reduced motion: drop the mask, blur, and
   cursor animation; fall back to a static caret. */
@media (prefers-reduced-motion: reduce) {
    .streaming-content.is-streaming {
        -webkit-mask-image: none;
        mask-image: none;
        filter: none;
    }
    .streaming-content.is-streaming::after {
        animation: none;
        opacity: 0.7;
    }
}

/* Optional: opt-in per-token fade. If you later wrap newly-arrived
   spans as `<span class="token">…</span>` during rendering, each
   chunk will ease in independently. Without the wrapper this rule
   is inert — kept here so the hook is already in place. */
.streaming-content .token {
    animation: token-fade-in 220ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes token-fade-in {
    from {
        opacity: 0;
        filter: blur(2.5px);
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        filter: blur(0);
        transform: translateY(0);
    }
}

/* External-call indicator: a stack of branded tool icons + shimmering
   label, shown while the LLM tool loop is dispatching MCP / webhook
   calls. Icons overlap like Claude's parallel-tool indicator. The theme
   exposes CSS variables as full color functions (e.g. `--primary: #404040`),
   so use them directly — wrapping them with `hsl()` produces invalid
   syntax and the rule is silently dropped. */
.external-call-shimmer {
    background-image: linear-gradient(90deg, var(--muted-foreground) 0%, var(--primary) 50%, var(--muted-foreground) 100%);
    background-size: 200% 100%;
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    animation: external-call-shimmer 2.4s linear infinite;
}

.external-call-icon {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    background-color: var(--background);
    box-shadow: 0 0 0 1px var(--border);
    overflow: hidden;
}

@keyframes external-call-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>
