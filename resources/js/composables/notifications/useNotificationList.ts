/**
 * One list of notifications (the bell's popover, the Notifications page):
 * its rows and next-page cursor, loading, marking read, and live updates
 * from the shared store. A list may start from a page the server already
 * rendered.
 */
import { fetchNotifications, markAllNotificationsRead, markNotificationRead, type NotificationFeed, type NotificationFilter } from '@/api/notifications';
import { onScopeDispose, ref, toValue, type MaybeRefOrGetter } from 'vue';
import { onNotificationChange, setUnreadCount, unreadCount, type UserNotification } from './notificationStore';

export function useNotificationList(filter: MaybeRefOrGetter<NotificationFilter>, initial?: NotificationFeed) {
    const items = ref<UserNotification[]>(initial?.notifications ?? []);
    const nextCursor = ref<string | null>(initial?.next_cursor ?? null);
    const loaded = ref(initial !== undefined);
    const loading = ref(false);
    const failed = ref(false);

    if (initial) setUnreadCount(initial.unread);

    async function load(): Promise<void> {
        loading.value = true;
        failed.value = false;
        try {
            const page = await fetchNotifications(toValue(filter));
            items.value = page.notifications;
            nextCursor.value = page.next_cursor;
            setUnreadCount(page.unread);
            loaded.value = true;
        } catch {
            failed.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function loadMore(): Promise<void> {
        if (!nextCursor.value || loading.value) return;

        loading.value = true;
        failed.value = false;
        try {
            const page = await fetchNotifications(toValue(filter), nextCursor.value);
            const known = new Set(items.value.map((item) => item.id));
            items.value = [...items.value, ...page.notifications.filter((item) => !known.has(item.id))];
            nextCursor.value = page.next_cursor;
            setUnreadCount(page.unread);
        } catch {
            failed.value = true;
        } finally {
            loading.value = false;
        }
    }

    function markLocallyRead(ids: string[] | null): void {
        items.value = items.value.map((item) => (ids === null || ids.includes(item.id) ? { ...item, read: true } : item));
    }

    async function markRead(notification: UserNotification): Promise<void> {
        if (notification.read) return;

        markLocallyRead([notification.id]);
        setUnreadCount(unreadCount.value - 1);
        try {
            await markNotificationRead(notification.id);
        } catch {
            // The next page load brings the server's count back.
        }
    }

    async function markAllRead(): Promise<void> {
        markLocallyRead(null);
        setUnreadCount(0);
        try {
            await markAllNotificationsRead();
        } catch {
            // The next page load brings the server's count back.
        }
    }

    const stopListening = onNotificationChange({
        pushed(notification) {
            if (!loaded.value) return;

            // A gathered notification moves to the top with its new count.
            items.value = [notification, ...items.value.filter((item) => item.id !== notification.id)];
        },
        read: markLocallyRead,
    });

    onScopeDispose(stopListening);

    return { items, nextCursor, loaded, loading, failed, load, loadMore, markRead, markAllRead };
}
