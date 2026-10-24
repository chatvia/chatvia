<script setup lang="ts">
/**
 * The hosted chat transcript: a loading state, then one bubble per message
 * (prompt, inline error, or assistant reply). Purely presentational — every
 * change is emitted to the page, which owns the transcript and the stream.
 */
import AssistantChatBubble from '@/components/chat/AssistantChatBubble.vue';
import ChatErrorBubble from '@/components/chat/ChatErrorBubble.vue';
import UserChatBubble from '@/components/chat/UserChatBubble.vue';
import type { ActiveToolCall, Message, MessageSource } from '@/types/chat';
import type { ToolApprovalCall, ToolArtifact } from '@/types/stream';
import { toRaw } from 'vue';
import { useI18n } from 'vue-i18n';

type MessageId = string | number;

const props = defineProps<{
    messages: Message[];
    loading: boolean;
    /** Wider column when the visitor expanded the chat. */
    expanded: boolean;
    conversationId: string;
    avatar?: string | null;
    isMessageStreaming: (message: Message) => boolean;
    hasActiveCalls: boolean;
    activeCalls: ActiveToolCall[];
    sourcesFor: (message: Message) => MessageSource[];
    artifactsFor: (message: Message) => ToolArtifact[];
    approvalsFor: (assistantMessageId: MessageId | null | undefined) => ToolApprovalCall[];
    resolvingApprovalIds: string[];
}>();

const emit = defineEmits<{
    messageEdited: [userMessageId: MessageId, assistantMessageId: MessageId | undefined, newContent: string];
    messageDeleted: [id: MessageId];
    attachmentDeleted: [messageId: MessageId, attachmentId: string];
    regenerate: [userMessageId: MessageId, assistantMessageId: MessageId, onDone: () => void];
    retry: [index: number];
    approveTool: [call: ToolApprovalCall];
    denyTool: [call: ToolApprovalCall];
}>();

const { t } = useI18n();

// Message languages written right to left; their bubbles flip direction.
const RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv'];

const isMessageRTL = (message: Message): boolean => !!message.language && RTL_LANGUAGES.includes(message.language);

// A row keeps its key when the server assigns the message id at `start`, so
// a streaming reply is not re-mounted (and faded in again) halfway through.
const rowKeys = new WeakMap<Message, string>();
let nextRowKey = 0;

function rowKey(message: Message): string {
    const raw = toRaw(message);
    let key = rowKeys.get(raw);
    if (!key) {
        key = `row-${nextRowKey++}`;
        rowKeys.set(raw, key);
    }

    return key;
}

// More space above a message when the speaker changes than between two from the same side.
function rowClass(index: number, message: Message) {
    return [
        'mx-auto px-4 sm:px-8',
        props.expanded ? 'max-w-6xl' : 'max-w-3xl',
        index === 0 ? 'pt-6' : '',
        index > 0 && props.messages[index - 1].role !== message.role ? 'pt-6' : 'pt-3',
        'pb-3',
    ];
}

/** The stored reply paired with the prompt at `index`, if one follows it (a stub has id 0). */
function replyIdAfter(index: number): MessageId | undefined {
    const next = props.messages[index + 1];

    return next?.role === 'assistant' && next.id ? next.id : undefined;
}

/** The prompt paired with the reply at `index`, if one precedes it. */
function promptIdBefore(index: number): MessageId | undefined {
    const previous = index > 0 ? props.messages[index - 1] : undefined;

    return previous?.role === 'user' ? (previous.id ?? undefined) : undefined;
}
</script>

<template>
    <div v-if="loading" class="flex h-full items-center justify-center">
        <div class="flex flex-col items-center gap-3">
            <div class="flex gap-1">
                <div class="bg-muted-foreground/40 h-2 w-2 animate-bounce rounded-full [animation-delay:-0.3s]"></div>
                <div class="bg-muted-foreground/40 h-2 w-2 animate-bounce rounded-full [animation-delay:-0.15s]"></div>
                <div class="bg-muted-foreground/40 h-2 w-2 animate-bounce rounded-full"></div>
            </div>
            <p class="text-muted-foreground text-xs">{{ t('chat.loading_conversation') }}</p>
        </div>
    </div>

    <!-- Rows added after the transcript loaded fade in; the loaded ones do not. -->
    <TransitionGroup v-else name="chat-row" tag="div" class="min-h-full">
        <div v-for="(message, index) in messages" :id="`message-${index}`" :key="rowKey(message)" class="w-full">
            <div :class="rowClass(index, message)">
                <UserChatBubble
                    v-if="message.role === 'user'"
                    :message="message"
                    :assistant-message-id="replyIdAfter(index)"
                    :conversation-id="conversationId"
                    :isRTL="isMessageRTL(message)"
                    @message-deleted="(id) => emit('messageDeleted', id)"
                    @message-edited="(userId, assistantId, content) => emit('messageEdited', userId, assistantId, content)"
                    @attachment-deleted="(messageId, attachmentId) => emit('attachmentDeleted', messageId, attachmentId)"
                />
                <ChatErrorBubble v-else-if="message.role === 'error'" :message="message" @retry="emit('retry', index)" />
                <AssistantChatBubble
                    v-else
                    :message="message"
                    :user-message-id="promptIdBefore(index)"
                    :sources="sourcesFor(message)"
                    :artifacts="artifactsFor(message)"
                    :conversation-id="conversationId"
                    :hide-feedback="isMessageStreaming(message)"
                    :is-thinking="isMessageStreaming(message) && !message.content"
                    :is-streaming="isMessageStreaming(message)"
                    :is-latest="index === messages.length - 1"
                    :is-calling-external="isMessageStreaming(message) && hasActiveCalls"
                    :active-calls="isMessageStreaming(message) ? activeCalls : []"
                    :avatar="avatar"
                    :isRTL="isMessageRTL(message)"
                    :pending-approvals="approvalsFor(message.id)"
                    :resolving-approval-ids="resolvingApprovalIds"
                    @message-deleted="(id) => emit('messageDeleted', id)"
                    @regenerate="(userId, assistantId, onDone) => emit('regenerate', userId, assistantId, onDone)"
                    @approve-tool="(call) => emit('approveTool', call)"
                    @deny-tool="(call) => emit('denyTool', call)"
                />
            </div>
        </div>
    </TransitionGroup>
</template>
