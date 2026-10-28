/**
 * State of the Inbox page: the live list (refreshed every few seconds while
 * the tab is visible, and at once when a handoff request arrives), the open
 * chat with its messages, and the operator actions. Sending a message in a
 * chat the operator does not hold takes it over first, so there is no
 * separate "Take over" step. The open chat goes live through `useHandoff`
 * (presence, typing, new messages); every call goes through the chat's own
 * agent routes, so the per-agent permission checks still apply.
 */
import { fetchChatLogMessages, type ChatLogMessage } from '@/dashboard/api/chatLog';
import { fetchInboxConversations, type InboxConversation } from '@/dashboard/api/inbox';
import { setInboxWaitingCount } from '@/dashboard/composables/inbox/inboxBadge';
import { handoffEventToMessage, handoffStateFrom, useHandoff } from '@/dashboard/composables/useHandoff';
import { inbox as inboxPage } from '@/routes';
import { onNotificationChange } from '@/shared/composables/notifications/notificationStore';
import { router, usePage } from '@inertiajs/vue3';
import { computed, inject, onMounted, onUnmounted, ref, watch, type InjectionKey } from 'vue';

export type InboxFilter = 'all' | 'waiting' | 'mine';

/** How often the list refreshes while the page is visible. */
const REFRESH_MS = 15_000;

export function useInbox() {
    const page = usePage();
    const myId = () => String(page.props.auth.user.id);

    const conversations = ref<InboxConversation[]>([]);
    const isLoadingList = ref(true);
    const listFailed = ref(false);
    const filter = ref<InboxFilter>('all');
    const search = ref('');

    const selectedId = ref<string | null>(new URL(window.location.href).searchParams.get('conversation'));
    // The last known row of the open chat, kept when it drops off the list
    // (the visitor left), so the thread stays readable.
    const selectedSnapshot = ref<InboxConversation | null>(null);

    const messages = ref<ChatLogMessage[]>([]);
    const messagesPage = ref(1);
    const isLoadingMessages = ref(false);
    const replyText = ref('');

    const selected = computed(() => conversations.value.find((row) => row.conversation_id === selectedId.value) ?? selectedSnapshot.value);

    const handoff = useHandoff(
        () => selected.value?.agent.id ?? null,
        () => selectedId.value,
    );

    const heldByMe = computed(() => handoff.isHandedOff.value && String(handoff.handoffState.value.adminId) === myId());
    const heldByOther = computed(() => handoff.isHandedOff.value && !heldByMe.value);
    const canReply = computed(() => (selected.value?.can_join ?? false) && !heldByOther.value);

    const waitingCount = computed(() => conversations.value.filter((row) => row.handoff_status === 'pending').length);
    const mineCount = computed(() => conversations.value.filter((row) => row.held_by_me).length);

    const visibleConversations = computed(() => {
        const term = search.value.trim().toLowerCase();

        return conversations.value.filter((row) => {
            if (filter.value === 'waiting' && row.handoff_status !== 'pending') return false;
            if (filter.value === 'mine' && !row.held_by_me) return false;
            if (!term) return true;

            return [row.visitor_name, row.visitor_email, row.agent.name, row.last_message].some((text) => text?.toLowerCase().includes(term));
        });
    });

    let listVersion = 0;

    async function loadList() {
        const version = ++listVersion;

        try {
            const result = await fetchInboxConversations();
            if (version !== listVersion) return;

            conversations.value = result.conversations;
            setInboxWaitingCount(result.waiting_count);
            listFailed.value = false;

            const row = result.conversations.find((candidate) => candidate.conversation_id === selectedId.value);
            if (row) selectedSnapshot.value = row;
        } catch {
            if (version === listVersion) listFailed.value = true;
        } finally {
            if (version === listVersion) isLoadingList.value = false;
        }
    }

    /** Loads the newest page of the open chat's messages (the list pages oldest first). */
    async function loadMessages() {
        const row = selected.value;
        if (!row) return;

        isLoadingMessages.value = true;

        try {
            let result = await fetchChatLogMessages(row.agent.id, row.conversation_id, 1);
            if (result.messages.last_page > 1) {
                result = await fetchChatLogMessages(row.agent.id, row.conversation_id, result.messages.last_page);
            }
            if (selectedId.value !== row.conversation_id) return;

            messages.value = result.messages.data;
            messagesPage.value = result.messages.current_page;

            if (result.handoff) handoff.handoffState.value = handoffStateFrom(result.handoff);
            if (heldByMe.value) void handoff.markMessagesAsRead();

            // Only the widget has a live relay, so only its chats have anyone to watch.
            if (row.source === 'widget') handoff.joinChannel(row.conversation_id);
        } catch {
            messages.value = [];
        } finally {
            isLoadingMessages.value = false;
        }
    }

    async function loadEarlierMessages() {
        const row = selected.value;
        if (!row || messagesPage.value <= 1) return;

        try {
            const result = await fetchChatLogMessages(row.agent.id, row.conversation_id, messagesPage.value - 1);
            if (selectedId.value !== row.conversation_id) return;

            const known = new Set(messages.value.map((message) => message.id));
            messages.value = [...result.messages.data.filter((message) => !known.has(message.id)), ...messages.value];
            messagesPage.value = result.messages.current_page;
        } catch {
            // The button stays, so the operator can try again.
        }
    }

    function appendMessage(message: ChatLogMessage) {
        if (messages.value.some((existing) => existing.id === message.id)) return;

        messages.value.push(message);
    }

    function select(conversationId: string | null) {
        if (conversationId === selectedId.value) return;

        selectedId.value = conversationId;
        selectedSnapshot.value = conversations.value.find((row) => row.conversation_id === conversationId) ?? null;
        messages.value = [];
        replyText.value = '';

        router.replace({
            url: inboxPage.url(conversationId ? { query: { conversation: conversationId } } : undefined),
            preserveScroll: true,
            preserveState: true,
        });

        if (conversationId) void loadMessages();
    }

    /** Marks the open chat's row as held by the operator, until the next refresh says otherwise. */
    function markRowHeld(held: boolean) {
        const row = conversations.value.find((candidate) => candidate.conversation_id === selectedId.value);
        if (!row) return;

        row.held_by_me = held;
        row.holder_name = null;
        row.handoff_status = held ? 'human' : 'ai';
    }

    // useHandoff shows request errors itself; a failed send puts the text back.
    async function send(content: string) {
        try {
            if (!heldByMe.value) {
                await handoff.takeOver();
                markRowHeld(true);
            }

            const sent = await handoff.sendMessage(content);
            if (sent) {
                appendMessage(handoffEventToMessage({ id: sent.id, content: sent.content, role: sent.role, timestamp: sent.created_at }));
            }
        } catch {
            replyText.value = content;
        }
    }

    async function giveBackToAi() {
        const row = selected.value;

        try {
            await handoff.returnToAi();
            markRowHeld(false);
            // Handing back leaves the channel; stay on it to keep seeing the visitor.
            if (row?.source === 'widget') handoff.joinChannel(row.conversation_id);
        } catch {
            // Shown by useHandoff.
        }
    }

    function sendTyping(isTyping: boolean) {
        if (heldByMe.value) void handoff.sendTyping(isTyping);
    }

    // Live messages of the open chat are appended without a refetch; the
    // visitor's count as read at once while the operator holds the chat.
    watch(
        () => handoff.newMessageReceived.value,
        (event) => {
            if (!event || !selectedId.value) return;

            appendMessage(handoffEventToMessage(event));
            if (event.role === 'user' && heldByMe.value) void handoff.markMessagesAsRead();

            const row = conversations.value.find((candidate) => candidate.conversation_id === selectedId.value);
            if (row) {
                row.last_message = event.content;
                row.last_message_role = event.role as InboxConversation['last_message_role'];
                row.last_activity_at = event.timestamp ?? new Date().toISOString();
            }
        },
    );

    let refreshTimer: ReturnType<typeof setInterval> | null = null;
    let stopNotifications: (() => void) | null = null;

    function refreshWhenVisible() {
        if (document.visibilityState === 'visible') void loadList();
    }

    onMounted(async () => {
        await loadList();
        if (selectedId.value) void loadMessages();

        refreshTimer = setInterval(refreshWhenVisible, REFRESH_MS);
        document.addEventListener('visibilitychange', refreshWhenVisible);
        stopNotifications = onNotificationChange({
            pushed: (notification) => {
                if (notification.kind === 'handoff_requested') void loadList();
            },
        });
    });

    onUnmounted(() => {
        if (refreshTimer) clearInterval(refreshTimer);
        document.removeEventListener('visibilitychange', refreshWhenVisible);
        stopNotifications?.();
    });

    return {
        conversations,
        visibleConversations,
        isLoadingList,
        listFailed,
        filter,
        search,
        waitingCount,
        mineCount,
        selectedId,
        selected,
        messages,
        hasEarlierMessages: computed(() => messagesPage.value > 1),
        isLoadingMessages,
        replyText,
        handoff,
        heldByMe,
        heldByOther,
        canReply,
        loadList,
        loadMessages,
        loadEarlierMessages,
        select,
        send,
        giveBackToAi,
        sendTyping,
    };
}

export type Inbox = ReturnType<typeof useInbox>;

/** The page provides its Inbox state under this key; the list and thread inject it. */
export const inboxKey: InjectionKey<Inbox> = Symbol('inbox');

export function injectInbox(): Inbox {
    const inbox = inject(inboxKey);
    if (!inbox) throw new Error('injectInbox() needs the Inbox page to provide its state.');

    return inbox;
}
