/**
 * The visitor's conversation list for the hosted chat sidebar: loading,
 * server search, rename and delete. Reloads whenever the chat page reports a
 * newly named conversation on the chat event bus.
 */
import { deleteAllConversations, deleteConversation, fetchConversations, renameConversation, searchConversations } from '@/chat/api/chat';
import type { ChatConversationSummary } from '@/shared/types/chat';
import { useChatEventBus } from '@/shared/utils/eventBus';
import { usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * The last list loaded, per person and chat (path up to the agent slug). Each chat page
 * mounts a new sidebar, so without it the list blanks to a loader on every
 * conversation the visitor opens; with it the old list shows while the fresh
 * one loads.
 */
const lastLoaded = new Map<string, ChatConversationSummary[]>();

export function useChatConversations() {
    const bus = useChatEventBus();
    const page = usePage();

    // Keyed to the person too: another sign-in in the same tab never sees this list.
    const chatKey = () => `${page.props.auth?.user?.id ?? 'guest'}:${window.location.pathname.split('/').slice(0, 3).join('/')}`;

    const cached = lastLoaded.get(chatKey());
    const conversations = ref<ChatConversationSummary[]>(cached ?? []);
    const isLoading = ref(cached === undefined);

    async function load() {
        const key = chatKey();
        const list = await fetchConversations();
        lastLoaded.set(key, list);
        conversations.value = list;
        isLoading.value = false;
    }

    // Shared with the ⌘K palette: matches titles and message content (full-text).
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
            lastLoaded.delete(chatKey());

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
