/**
 * Streams one assistant turn at a time over SSE and routes every envelope
 * event to the right message: new, edited and regenerated turns, and the
 * continuation after a gated tool call is approved or denied. Tracks which
 * reply is streaming, the tool calls in flight, pending approval cards and a
 * screen-reader status line.
 */
import { chatStreamUrl, generateConversationTitle, toolApprovalResolveUrl } from '@/api/chat';
import { stopTurn } from '@/api/chatTurns';
import { useChatStream } from '@/composables/useChatStream';
import type { ChatAttachment, Message, MessageSource } from '@/types/chat';
import type { StreamCallbacks, StreamError, StreamStartData, StreamToolData } from '@/types/stream';
import { useChatEventBus } from '@/utils/eventBus';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { useActiveToolCalls } from './useActiveToolCalls';
import type { ChatMessages, MessageId, TurnSlots } from './useChatMessages';
import type { ChatScroll } from './useChatScroll';
import { useToolApprovals, type ApprovalDecisions } from './useToolApprovals';

export interface StartTurnOptions extends TurnSlots {
    message: string;
    saveUserMessageImmediately?: boolean;
    /** Original timestamp of a regenerated prompt, so it keeps its place in history. */
    createdAt?: string | null;
    isNewConversation?: boolean;
    autoScrollOnDone?: boolean;
    attachments?: ChatAttachment[];
}

interface TurnTarget extends TurnSlots {
    isNewConversation: boolean;
    autoScrollOnDone: boolean;
}

interface StreamTurnDeps {
    conversationId: string;
    transcript: ChatMessages;
    scroll: ChatScroll;
    /** The model the chatter picked (sent as `model_id`); null answers with the agent's own. */
    modelId?: () => number | null;
}

/** Home sends the visitor here with `?new=1` right after creating the conversation. */
const NEW_CONVERSATION_PARAM = 'new';

const TITLE_RETRY_DELAY_MS = 1000;

export function isNewConversationVisit(): boolean {
    return new URLSearchParams(window.location.search).get(NEW_CONVERSATION_PARAM) === '1';
}

function clearNewConversationFlag() {
    if (!isNewConversationVisit()) return;

    const url = new URL(window.location.href);
    url.searchParams.delete(NEW_CONVERSATION_PARAM);
    window.history.replaceState({}, '', url);
}

function newRequestId(): string {
    return window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function useChatStreamTurn({ conversationId, transcript, scroll, modelId = () => null }: StreamTurnDeps) {
    const { t } = useI18n();
    const bus = useChatEventBus();
    const stream = useChatStream();
    const toolCalls = useActiveToolCalls();
    const approvals = useToolApprovals(resumeAfterApproval);

    // UUID of the reply being streamed, known once the server's `start` event arrives.
    const streamingAssistantId = ref<MessageId | null>(null);
    // The request id of the turn being streamed; a resumed turn keeps its
    // paused turn's id. Stop names the turn with it.
    let runningRequestId: string | null = null;
    // Updated on discrete events only (start, each tool call, completion), never per token.
    const liveAnnouncement = ref('');

    const isStreaming = computed(() => stream.state.value === 'streaming' || stream.state.value === 'connecting');

    /** Before `start` the reply stub still has id 0, so fall back to "the empty assistant row". */
    function isMessageStreaming(message: Message): boolean {
        if (!isStreaming.value) return false;
        if (streamingAssistantId.value) return message.id === streamingAssistantId.value;

        return message.role === 'assistant' && (message.id === 0 || message.id === null);
    }

    // Errors normally render inline (handleError). A toast is the fallback when
    // the reply already had text, which an inline card would overwrite.
    watch(stream.error, (err) => {
        if (!err) return;

        const last = transcript.messages.value[transcript.messages.value.length - 1];
        if (last?.role !== 'error') toast.error(err.message);
    });

    /** Single entry point for new, edited and regenerated turns. */
    function startTurn(options: StartTurnOptions) {
        const requestId = newRequestId();
        runningRequestId = requestId;
        // The first reply is asked for once: a reload from here on waits for
        // it instead of asking again (see Chat.vue).
        if (options.isNewConversation) clearNewConversationFlag();
        streamingAssistantId.value = null;
        approvals.reset();

        // The reply shows the logo of the model asked for (Multi-model); a
        // reload reads the stored one.
        const model = modelId();
        const reply = transcript.messages.value[options.assistantIndex];
        if (reply && model !== null) reply.llm_id = model;

        const payload = JSON.stringify({
            message: options.message,
            save_user_message_immediately: options.saveUserMessageImmediately ?? false,
            created_at: options.createdAt ?? null,
            request_id: requestId,
            attachments: options.attachments ?? [],
            model_id: model,
        });

        stream.start(
            chatStreamUrl(conversationId),
            payload,
            buildCallbacks({
                userIndex: options.userIndex,
                assistantIndex: options.assistantIndex,
                isNewConversation: options.isNewConversation ?? false,
                autoScrollOnDone: options.autoScrollOnDone ?? true,
            }),
        );
    }

    /** The continuation streams under the original reply id; its `start` clears the cards. */
    function resumeAfterApproval(approvalId: string, assistantMessageId: MessageId, decisions: ApprovalDecisions) {
        const assistantIndex = transcript.indexOf(assistantMessageId);
        if (assistantIndex === -1) {
            approvals.clearFor(assistantMessageId);
            return;
        }

        scroll.follow();
        streamingAssistantId.value = null;

        stream.start(
            toolApprovalResolveUrl(approvalId),
            JSON.stringify({ decisions }),
            buildCallbacks({ userIndex: assistantIndex - 1, assistantIndex, isNewConversation: false, autoScrollOnDone: true }),
        );
    }

    function buildCallbacks(target: TurnTarget): StreamCallbacks {
        const streamingReply = () => transcript.findStreamingAssistant(streamingAssistantId.value, target.assistantIndex);

        return {
            onStart: (data) => handleStart(target, data),
            onDelta: (text) => {
                const reply = streamingReply();
                if (!reply) return;
                reply.content += text;
                scroll.scrollWhileStreaming();
            },
            onReasoning: (text) => {
                const reply = streamingReply();
                if (!reply) return;
                reply.reasoning = (reply.reasoning ?? '') + text;
                scroll.scrollWhileStreaming();
            },
            onReplace: (text) => {
                // An output guardrail corrected the answer.
                const reply = streamingReply();
                if (reply) reply.content = text;
            },
            onMeta: (key, value) => {
                // `generation_id` and `handoff` are handled by the widget and the chat log, not here.
                if (key !== 'language' || typeof value !== 'string') return;
                const reply = streamingReply();
                if (reply) reply.language = value;
            },
            onSources: (sources, id) => {
                if (id) transcript.setSources(id, sources as MessageSource[]);
            },
            onTool: (data, id) => handleTool(data, id, streamingReply()),
            onError: (err) => handleError(target, err),
            onDone: () => handleDone(target, streamingReply()),
        };
    }

    function handleStart(target: TurnTarget, data: StreamStartData) {
        const messages = transcript.messages.value;
        const reply = messages[target.assistantIndex];
        const prompt = messages[target.userIndex];

        if (reply?.role === 'assistant') reply.id = data.assistant_message_id;
        if (data.user_message_id && prompt?.role === 'user') prompt.id = data.user_message_id;

        // An input guardrail masked PII: swap the optimistic text for the stored version.
        if (typeof data.user_message === 'string' && prompt?.role === 'user' && prompt.content !== data.user_message) {
            prompt.content = data.user_message;
        }

        streamingAssistantId.value = data.assistant_message_id;
        // A (resumed) stream supersedes any approval cards on this reply.
        approvals.clearFor(data.assistant_message_id);
        liveAnnouncement.value = t('messages.responding');
    }

    function handleTool(data: StreamToolData, assistantMessageId: string, reply: Message | undefined) {
        if (!data?.tool_call_id) return;

        if (data.phase === 'awaiting_approval') {
            if (!assistantMessageId || !data.approval_id) return;
            if (reply) moveTextToSteps(reply);
            approvals.addPending(assistantMessageId, data);
            nextTick(() => scroll.scrollWhileStreaming());
            return;
        }

        if (data.phase === 'start') {
            if (reply) moveTextToSteps(reply);
            // Built-in capabilities (forms, charts, leads) are internal plumbing:
            // no "Calling …" indicator or step. Their artifacts still arrive on 'end'.
            if (data.internal) return;
            const call = toolCalls.start(data);
            reply?.steps?.push({ kind: 'tool', call, done: false });
            liveAnnouncement.value = data.activity_label || t('messages.calling', { label: call.label });
            return;
        }

        if (data.phase === 'end') {
            toolCalls.end(data.tool_call_id);
            const step = reply?.steps?.find((entry) => entry.kind === 'tool' && entry.call.tool_call_id === data.tool_call_id);
            if (step?.kind === 'tool') step.done = true;
            if (assistantMessageId && Array.isArray(data.artifacts) && data.artifacts.length > 0) {
                transcript.addArtifacts(assistantMessageId, data.artifacts);
            }
        }
    }

    /**
     * Text streamed before a tool call is the model saying what it will do
     * next. The server drops it from the saved answer, so it becomes a step
     * of the chain and the answer starts again empty.
     */
    function moveTextToSteps(reply: Message) {
        reply.steps ??= [];
        const text = reply.content.trim();
        if (text !== '') reply.steps.push({ kind: 'text', text });
        reply.content = '';
    }

    function handleError(target: TurnTarget, err: StreamError) {
        // Show a typed error card (with CTA / retry) where the reply would have
        // been, instead of a transient toast.
        const messages = transcript.messages.value;
        const stub = messages[target.assistantIndex];
        if (stub?.role === 'assistant' && !stub.content) {
            messages[target.assistantIndex] = {
                ...stub,
                role: 'error',
                content: err.message,
                errorCode: err.code,
                errorRetryable: err.retryable,
            };
        }
        streamingAssistantId.value = null;

        // The prompt is already stored, so a failed first reply can still name
        // the chat; otherwise it stays "New Chat" for good. onDone and onError
        // never both fire for one turn.
        if (target.isNewConversation) void nameConversationThenRefresh();
    }

    function handleDone(target: TurnTarget, reply: Message | undefined) {
        streamingAssistantId.value = null;
        toolCalls.clear();
        // A stopped turn never sends the 'end' of a running call.
        reply?.steps?.forEach((step) => {
            if (step.kind === 'tool') step.done = true;
        });
        liveAnnouncement.value = t('messages.response_complete');

        if (target.isNewConversation) void nameConversationThenRefresh();
        clearNewConversationFlag();

        // The final render can add height (a closed code block, a table): glide to it.
        if (target.autoScrollOnDone) nextTick(() => scroll.scrollWhileStreaming());
    }

    /**
     * Names the conversation, then refreshes the sidebar. Titling is quick and
     * idempotent, so a single retry covers a transient failure (a 503 under
     * load, a network blip); the sidebar refreshes either way.
     */
    /**
     * Stop the turn being streamed. The server keeps a turn running when the
     * browser leaves, so closing the stream is not enough: the turn is asked
     * to end too, and keeps its partial answer. Resolves once it has ended,
     * so a following edit or regenerate runs after the stopped reply is saved.
     */
    async function stopAndWait(): Promise<void> {
        if (!isStreaming.value) return;

        const requestId = runningRequestId;
        stream.stop();

        if (requestId === null) return;

        try {
            await stopTurn(conversationId, requestId);
        } catch {
            // The turn still ends on its own; nothing to show.
        }
    }

    function stop() {
        void stopAndWait();
    }

    async function nameConversationThenRefresh() {
        for (let attempt = 0; attempt < 2; attempt++) {
            try {
                await generateConversationTitle(conversationId);
                break;
            } catch {
                if (attempt === 0) {
                    await new Promise((resolve) => setTimeout(resolve, TITLE_RETRY_DELAY_MS));
                }
            }
        }

        bus.emitConversationCreated(conversationId);
    }

    return {
        isStreaming,
        isMessageStreaming,
        liveAnnouncement,
        activeCalls: toolCalls.activeCalls,
        hasActiveCalls: toolCalls.hasActiveCalls,
        approvals,
        startTurn,
        stop,
        stopAndWait,
    };
}

export type ChatStreamTurn = ReturnType<typeof useChatStreamTurn>;
