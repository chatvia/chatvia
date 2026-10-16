/**
 * The notification center's shared state, one per tab: the unread count the
 * bell and the tab title show, and the person's switches for desktop alerts
 * and sound. The server's count arrives with every page (the `notifications`
 * shared prop, synced in app.ts); live changes arrive over Reverb and are
 * passed on to every open list through `onNotificationChange`.
 */
import { ref, watch } from 'vue';

export type UserNotification = Domain.Notification.Data.UserNotificationData;
export type NotificationPreferences = Domain.Notification.Data.NotificationPreferencesData;
type NotificationSummary = Domain.Notification.Data.NotificationSummaryData;

export const unreadCount = ref(0);
export const notificationPreferences = ref<NotificationPreferences | null>(null);

interface NotificationChangeListener {
    pushed?: (notification: UserNotification, isNew: boolean) => void;
    read?: (ids: string[] | null) => void;
}

const listeners = new Set<NotificationChangeListener>();

/** Hear about live changes; returns the function that stops listening. */
export function onNotificationChange(listener: NotificationChangeListener): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** The shared prop of a page; `undefined` (a partial reload without it) keeps the current state. */
export function syncNotificationSummary(summary: NotificationSummary | null | undefined): void {
    if (summary === undefined) return;

    unreadCount.value = summary?.unread ?? 0;
    notificationPreferences.value = summary?.preferences ?? null;
}

export function setUnreadCount(count: number): void {
    unreadCount.value = Math.max(0, count);
}

export function notificationPushed(notification: UserNotification, isNew: boolean): void {
    if (isNew && !notification.read) unreadCount.value += 1;

    listeners.forEach((listener) => listener.pushed?.(notification, isNew));
}

export function notificationsRead(ids: string[] | null, unread: number): void {
    setUnreadCount(unread);

    listeners.forEach((listener) => listener.read?.(ids));
}

// The tab title carries the unread count ("(3) Agents - Chatvia"). Inertia
// sets the title per page through pageTitle(); a change of count rewrites it.
const APP_NAME = 'Chatvia';
let baseTitle: string | null = null;

function withUnreadCount(title: string): string {
    if (unreadCount.value <= 0) return title;

    return `(${unreadCount.value > 99 ? '99+' : unreadCount.value}) ${title}`;
}

export function pageTitle(title: string): string {
    baseTitle = `${title} - ${APP_NAME}`;

    return withUnreadCount(baseTitle);
}

watch(unreadCount, () => {
    if (baseTitle !== null) document.title = withUnreadCount(baseTitle);
});
