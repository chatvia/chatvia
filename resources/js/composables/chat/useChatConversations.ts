/**
 * The visitor's conversation list for the hosted chat sidebar: loading,
 * server search, rename and delete. Reloads whenever the chat page reports a
 * newly named conversation on the chat event bus.
 */
import { deleteAllConversations, deleteConversation, fetchConversations, renameConversation, searchConversations } from '@/api/chat';
import type { ChatConversationSummary } from '@/types/chat';
import { useChatEventBus } from '@/utils/eventBus';
import { onMounted, onUnmounted, ref } from 'vue';

export function useChatConversations() {
    const bus = useChatEventBus();
    const conversations = ref<ChatConversationSummary[]>([]);
    const isLoading = ref(true);

    async function load() {
        isLoading.value = true;
        conversations.value = await fetchConversations();
        isLoading.value = false;
    }

    // Shared with the ⌘K palette: matches titles and message content (BM25).
    const search = (query: string): Promise<ChatConversationSummary[]> => searchConversations(query);

    /** Renames on the server, then mirrors the new title locally. Returns false on failure. */
    async function rename(conversationId: string | number, title: string): Promise<boolean> {
        try {
            await renameConversation(conversationId, title);
        } catch {
            return false;
        }

        const conversation = conversations.value.find((candidate) => candidate.id === conversationId);
        if (conversation) conversation.title = title;

        return true;
    }

    /** Deletes one conversation and reloads the list. Returns false on failure. */
    async function remove(conversationId: string | number): Promise<boolean> {
        try {
            await deleteConversation(conversationId);
        } catch {
            return false;
        }

        void load();

        return true;
    }

    async function removeAll(): Promise<boolean> {
        try {
            await deleteAllConversations();

            return true;
        } catch {
            return false;
        }
    }

    const reload = () => void load();

    onMounted(() => {
        void load();
        bus.onConversationCreated(reload);
    });

    onUnmounted(() => {
        bus.offConversationCreated(reload);
    });

    return { conversations, isLoading, search, rename, remove, removeAll };
}
