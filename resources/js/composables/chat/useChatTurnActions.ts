/**
 * What the visitor can do to the hosted chat transcript: send a message, edit
 * or regenerate a turn, retry a failed reply, and delete messages or
 * attachments. Each action updates the local transcript first and then
 * (re)starts the stream through `useChatStreamTurn`.
 */
import { regenerateMessage, type RegeneratedTurn } from '@/api/chat';
import type { ChatAttachment } from '@/types/chat';
import { router } from '@inertiajs/vue3';
import { isAxiosError } from 'axios';
import { nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { chatPages } from './chatPages';
import type { ChatMessages, MessageId } from './useChatMessages';
import type { ChatScroll } from './useChatScroll';
import { isNewConversationVisit, type ChatStreamTurn } from './useChatStreamTurn';

interface TurnActionDeps {
    conversationId: string;
    /** On the iframe-embed pages, whose "new chat" screen is the embed one. */
    embedded: boolean;
    transcript: ChatMessages;
    turn: ChatStreamTurn;
    scroll: ChatScroll;
}

export function useChatTurnActions({ conversationId, embedded, transcript, turn, scroll }: TurnActionDeps) {
    const { t } = useI18n();

    const goToNewChat = () => router.visit(chatPages(embedded).home());

    async function send(content: string, attachments: ChatAttachment[] = []) {
        const slots = transcript.appendTurn(content, attachments);
        scroll.follow();

        await nextTick();
        scroll.scrollToBottom();
        turn.startTurn({ message: content, ...slots, attachments, isNewConversation: isNewConversationVisit() });
    }

    /** Re-streams an edited prompt in place (used when the bubble has no paired reply id). */
    function editInPlace(messageId: MessageId, content: string) {
        const messages = transcript.messages.value;
        const userIndex = transcript.indexOf(messageId);
        if (userIndex === -1) return;

        const prompt = messages[userIndex];
        if (prompt.content === content) return;

        const assistantIndex = userIndex + 1;
        const reply = messages[assistantIndex];
        if (reply?.role !== 'assistant') return;

        const previousAssistantId = reply.id;
        prompt.content = content;
        reply.content = '';
        reply.id = 0;
        transcript.forgetReplyExtras(previousAssistantId);

        scroll.follow();
        turn.startTurn({
            message: content,
            userIndex,
            assistantIndex,
            updatedMessageInfo: { userMessageId: prompt.id, assistantMessageId: previousAssistantId },
        });
    }

    function editAndRegenerate(userMessageId: MessageId, assistantMessageId: MessageId, newContent: string) {
        return reissue(userMessageId, assistantMessageId, newContent);
    }

    async function regenerate(userMessageId: MessageId, assistantMessageId: MessageId, onDone?: () => void) {
        try {
            await reissue(userMessageId, assistantMessageId);
        } finally {
            onDone?.();
        }
    }

    /** Asks the server to drop the pair; null when the visitor was already told why it failed. */
    async function deletePairOnServer(userMessageId: MessageId, assistantMessageId: MessageId): Promise<RegeneratedTurn | null> {
        try {
            const result = await regenerateMessage({ conversationId, userMessageId, assistantMessageId });
            if (result?.user_message_content !== undefined) return result;
        } catch (error) {
            // 404: the conversation was deleted elsewhere (e.g. another tab).
            if (isAxiosError(error) && error.response?.status === 404) {
                toast.error(t('chat.conversation_gone'));
                goToNewChat();
                return null;
            }
        }

        toast.error(t('chat.regenerate_failed'));
        return null;
    }

    /**
     * Edit and regenerate share this flow: the server deletes the pair (and
     * returns the original timestamp), then a new reply streams into the same
     * slots while the edited prompt stays in view.
     */
    async function reissue(userMessageId: MessageId, assistantMessageId: MessageId, overrideContent: string | null = null) {
        const result = await deletePairOnServer(userMessageId, assistantMessageId);
        if (!result) return;

        const content = overrideContent ?? result.user_message_content;
        const userIndex = transcript.indexOf(userMessageId);
        const assistantIndex = transcript.indexOf(assistantMessageId);
        if (userIndex === -1 || assistantIndex === -1) {
            toast.error(t('chat.regenerate_failed'));
            return;
        }

        transcript.forgetReplyExtras(assistantMessageId);
        transcript.messages.value[userIndex] = { role: 'user', content, id: 0 };
        transcript.resetReply(assistantIndex);
        scroll.hold();

        await nextTick();
        document.getElementById(`message-${userIndex}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        turn.startTurn({
            message: content,
            userIndex,
            assistantIndex,
            saveUserMessageImmediately: true,
            createdAt: result.created_at,
            autoScrollOnDone: false,
        });
    }

    /**
     * The failed reply never started on the server, so there is nothing to
     * regenerate: put a fresh stub in its slot and stream the prompt again.
     */
    function retry(errorIndex: number) {
        const prompt = transcript.messages.value[errorIndex - 1];
        if (prompt?.role !== 'user') return;

        transcript.resetReply(errorIndex);
        scroll.follow();
        turn.startTurn({ message: prompt.content, userIndex: errorIndex - 1, assistantIndex: errorIndex });
    }

    function deleteMessage(messageId: MessageId) {
        if (transcript.removePair(messageId)) goToNewChat();
    }

    function deleteAttachment(messageId: MessageId, attachmentId: string) {
        transcript.removeAttachment(messageId, attachmentId);
    }

    return { send, editInPlace, editAndRegenerate, regenerate, retry, deleteMessage, deleteAttachment, goToNewChat };
}
