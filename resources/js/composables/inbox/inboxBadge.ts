/**
 * The Inbox link's badge: how many chats wait for a human, one count per tab.
 * It loads once, then again whenever the notification center hears about a
 * handoff (a new request, or one read or cleared because someone took it),
 * so it rides the live channel the bell already listens on. The Inbox page
 * also passes on the count it gets with every list refresh.
 */
import { fetchInboxWaitingCount } from '@/api/inbox';
import { onNotificationChange } from '@/composables/notifications/notificationStore';
import { ref } from 'vue';

export const inboxWaitingCount = ref(0);

let started = false;

export function setInboxWaitingCount(count: number): void {
    inboxWaitingCount.value = count;
}

async function refresh(): Promise<void> {
    try {
        setInboxWaitingCount(await fetchInboxWaitingCount());
    } catch {
        // The badge keeps its last count; the next change retries.
    }
}

/** Starts the badge once per tab (later calls do nothing). */
export function startInboxBadge(): void {
    if (started) return;
    started = true;

    void refresh();
    onNotificationChange({
        pushed: (notification) => {
            if (notification.kind === 'handoff_requested') void refresh();
        },
        read: () => void refresh(),
        cleared: () => void refresh(),
    });
}
