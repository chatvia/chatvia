/**
 * Live-chat handoff for the chat log: joins the conversation's presence
 * channel (who is online, typing, new messages, handoff status) and wraps the
 * operator actions (take over, reply, return to AI). Server calls go through
 * `@/api/handoff`; presence uses the app-wide Echo connection from app.ts.
 */
import type { ChatLogHandoffStatus, ChatLogMessage } from '@/api/chatLog';
import {
    markHandoffMessagesRead,
    returnConversationToAi,
    sendHandoffMessage,
    sendHandoffTyping,
    takeOverConversation,
    type HandoffMessageResult,
    type HandoffReturnResult,
    type HandoffTakeoverResult,
} from '@/api/handoff';
import { errorMessage } from '@/api/http';
import { i18n } from '@/i18n';
import { echo } from '@laravel/echo-vue';
import { computed, onUnmounted, ref, watch } from 'vue';

export interface HandoffState {
    status: 'ai' | 'pending' | 'human' | 'returned';
    adminId: string | number | null;
    adminName: string | null;
    handoffAt: string | null;
}

interface PresenceMember {
    id: string;
    name: string;
    type: 'user' | 'agent';
}

interface TypingState {
    isTyping: boolean;
    typerType: 'user' | 'agent';
    typerName: string | null;
}

/** `.message` broadcast (Domain\Conversation\Events\MessagePosted). */
export interface HandoffMessageEvent {
    id: string;
    conversation_id?: string;
    chat_conversation_id?: string;
    content: string;
    role: string;
    sender_name?: string | null;
    sender_id?: string | number | null;
    timestamp?: string;
    artifacts?: { kind: string; data: unknown }[] | null;
}

/** `.typing` broadcast or client whisper (Domain\Conversation\Events\TypingStarted). */
interface TypingEvent {
    is_typing: boolean;
    typer_type: 'user' | 'agent';
    typer_name: string | null;
}

/** `.handoff` broadcast (Domain\Conversation\Events\HandedOff). */
interface HandoffEvent {
    status: HandoffState['status'];
    admin_id: string | number | null;
    admin_name: string | null;
    timestamp: string | null;
}

/** The server's handoff state of a conversation (with its messages) as the composable keeps it. */
export function handoffStateFrom(status: ChatLogHandoffStatus): HandoffState {
    return {
        status: (status.status || 'ai') as HandoffState['status'],
        adminId: status.admin_id || null,
        adminName: status.admin_name || null,
        handoffAt: status.handoff_at || null,
    };
}

/**
 * A pushed `.message` event as a chat-log message, so a live chat can append
 * it without a refetch. Fields the event does not carry stay empty.
 */
export function handoffEventToMessage(event: HandoffMessageEvent): ChatLogMessage {
    return {
        id: event.id,
        chat_conversation_id: event.chat_conversation_id ?? '',
        content: event.content,
        role: event.role,
        sentiment: null,
        tone: null,
        feedback: null,
        is_response_from_qa: false,
        response_time_ms: null,
        prompt_tokens: null,
        completion_tokens: null,
        llm_name: null,
        created_at: event.timestamp || new Date().toISOString(),
        agent_question_answer_id: null,
        artifacts: event.artifacts ?? null,
        source: null,
        read_at: null,
        confidence_score: null,
        voice: false,
        trace_id: null,
    };
}

/** How long a "visitor is typing" signal lasts without a follow-up. */
const TYPING_TIMEOUT_MS = 3000;

const AI_STATE: HandoffState = { status: 'ai', adminId: null, adminName: null, handoffAt: null };

export function useHandoff(agentId: () => string | null, conversationId: () => string | null) {
    const t = i18n.global.t;

    const handoffState = ref<HandoffState>({ ...AI_STATE });
    const presenceMembers = ref<PresenceMember[]>([]);
    const typingState = ref<TypingState | null>(null);
    const isConnected = ref(false);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const adminMessage = ref('');
    const isSendingMessage = ref(false);
    // Changes on every `.message` broadcast so watchers can react to new messages.
    const newMessageReceived = ref<(HandoffMessageEvent & { receivedAt: number }) | null>(null);

    let currentChannelName: string | null = null;
    let typingTimeout: ReturnType<typeof setTimeout> | null = null;
    let onMessageCallback: ((data: HandoffMessageEvent) => void) | null = null;

    const isHandedOff = computed(() => handoffState.value.status === 'human');
    const canTakeOver = computed(() => handoffState.value.status !== 'human');
    const canReturn = computed(() => handoffState.value.status === 'human');
    const userIsOnline = computed(() => presenceMembers.value.some((member) => member.type === 'user'));
    const onlineUser = computed(() => presenceMembers.value.find((member) => member.type === 'user'));
    const userIsTyping = computed(() => typingState.value?.isTyping && typingState.value.typerType === 'user');

    /** The agent + conversation the operator acts on, or null when nothing is selected. */
    function currentTarget(): { agentId: string; conversationId: string } | null {
        const agent = agentId();
        const conversation = conversationId();

        return agent && conversation ? { agentId: agent, conversationId: conversation } : null;
    }

    // Only the visitor's typing is shown; the operator's own whispers are ignored.
    function handleTypingEvent(data: TypingEvent) {
        if (data.typer_type !== 'user') return;

        typingState.value = { isTyping: data.is_typing, typerType: data.typer_type, typerName: data.typer_name };

        if (typingTimeout) clearTimeout(typingTimeout);
        if (data.is_typing) {
            typingTimeout = setTimeout(() => {
                if (typingState.value?.typerType === 'user') {
                    typingState.value = null;
                }
            }, TYPING_TIMEOUT_MS);
        }
    }

    function joinChannel(convId: string) {
        if (!convId) return;

        const channelName = `chat.${convId}`;
        if (currentChannelName === channelName && isConnected.value) return;

        leaveChannel();
        currentChannelName = channelName;

        echo()
            .join(channelName)
            .here((members: PresenceMember[]) => {
                presenceMembers.value = members;
                isConnected.value = true;
            })
            .joining((member: PresenceMember) => {
                if (!presenceMembers.value.find((existing) => existing.id === member.id)) {
                    presenceMembers.value.push(member);
                }
            })
            .leaving((member: PresenceMember) => {
                presenceMembers.value = presenceMembers.value.filter((existing) => existing.id !== member.id);
            })
            .listen('.message', (data: HandoffMessageEvent) => {
                newMessageReceived.value = { ...data, receivedAt: Date.now() };
                onMessageCallback?.(data);
            })
            .listen('.typing', handleTypingEvent)
            .listenForWhisper('typing', handleTypingEvent)
            .listen('.handoff', (data: HandoffEvent) => {
                handoffState.value = {
                    status: data.status,
                    adminId: data.admin_id,
                    adminName: data.admin_name,
                    handoffAt: data.timestamp,
                };
            })
            .error(() => {
                error.value = t('agents.chatlog.handoff.errors.connect_failed');
                isConnected.value = false;
            });
    }

    function leaveChannel() {
        if (!currentChannelName) return;

        echo().leave(currentChannelName);
        currentChannelName = null;
        isConnected.value = false;
        presenceMembers.value = [];
        typingState.value = null;
    }

    async function takeOver(): Promise<HandoffTakeoverResult | undefined> {
        const target = currentTarget();
        if (!target) return;

        isLoading.value = true;
        error.value = null;

        try {
            const result = await takeOverConversation(target);
            handoffState.value = {
                status: 'human',
                adminId: result.admin_id ?? null,
                adminName: result.admin_name ?? null,
                handoffAt: new Date().toISOString(),
            };
            joinChannel(target.conversationId);

            return result;
        } catch (err) {
            error.value = errorMessage(err, t('agents.chatlog.handoff.errors.takeover_failed'));
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function returnToAi(): Promise<HandoffReturnResult | undefined> {
        const target = currentTarget();
        if (!target) return;

        isLoading.value = true;
        error.value = null;

        try {
            const result = await returnConversationToAi(target);
            handoffState.value = { ...AI_STATE };
            leaveChannel();

            return result;
        } catch (err) {
            error.value = errorMessage(err, t('agents.chatlog.handoff.errors.return_failed'));
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function sendMessage(content: string): Promise<HandoffMessageResult['message'] | undefined> {
        const target = currentTarget();
        if (!target || !content.trim()) return;

        isSendingMessage.value = true;
        error.value = null;

        try {
            const result = await sendHandoffMessage(target, content);
            adminMessage.value = '';

            return result.message;
        } catch (err) {
            error.value = errorMessage(err, t('agents.chatlog.handoff.errors.send_failed'));
            throw err;
        } finally {
            isSendingMessage.value = false;
        }
    }

    // Typing and read receipts are best-effort: a failure must not interrupt the operator.
    async function sendTyping(isTyping: boolean) {
        const target = currentTarget();
        if (!target) return;

        try {
            await sendHandoffTyping(target, isTyping);
        } catch {
            // Ignored on purpose (see above).
        }
    }

    async function markMessagesAsRead() {
        const target = currentTarget();
        if (!target) return;

        try {
            await markHandoffMessagesRead(target);
        } catch {
            // Ignored on purpose (see sendTyping).
        }
    }

    // A different conversation starts from a clean slate. The channel is joined
    // again once its messages load (see useChatLog.fetchMessages).
    watch(
        () => conversationId(),
        (_newId, oldId) => {
            if (oldId) leaveChannel();

            handoffState.value = { ...AI_STATE };
            presenceMembers.value = [];
            typingState.value = null;
            error.value = null;
        },
    );

    function onMessage(callback: (data: HandoffMessageEvent) => void) {
        onMessageCallback = callback;
    }

    onUnmounted(() => {
        leaveChannel();
        if (typingTimeout) clearTimeout(typingTimeout);
        onMessageCallback = null;
    });

    return {
        // State
        handoffState,
        presenceMembers,
        typingState,
        isConnected,
        isLoading,
        error,
        adminMessage,
        isSendingMessage,
        newMessageReceived,

        // Computed
        isHandedOff,
        canTakeOver,
        canReturn,
        userIsOnline,
        onlineUser,
        userIsTyping,

        // Actions
        takeOver,
        returnToAi,
        sendMessage,
        sendTyping,
        markMessagesAsRead,
        joinChannel,
        leaveChannel,
        onMessage,
    };
}
