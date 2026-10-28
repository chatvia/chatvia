/**
 * The notification center API: a page of the signed-in person's
 * notifications, and marking them read. New notifications and reads from
 * other tabs arrive over Reverb (see `useNotificationStream`).
 */
import notifications from '@/routes/notifications';
import { del, get, patch, post } from '@/shared/api/http';

export type NotificationFeed = App.Http.Resources.Notification.NotificationFeedResourceData;
export type NotificationFilter = Domain.Notification.Enums.NotificationFilter;

export function fetchNotifications(filter: NotificationFilter, cursor: string | null = null): Promise<NotificationFeed> {
    return get<NotificationFeed>(notifications.feed.url({ query: { filter, ...(cursor ? { cursor } : {}) } }));
}

export function markNotificationRead(id: string): Promise<void> {
    return patch(notifications.update.url(id));
}

export function markAllNotificationsRead(): Promise<void> {
    return post(notifications.readAll.url());
}

export function clearNotifications(): Promise<void> {
    return del(notifications.clear.url());
}
