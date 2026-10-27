/**
 * The notification center's shared state, one per tab: the unread count the
 * bell and the tab title show, and the person's switches for desktop alerts
 * and sound. The server's count arrives with every page (the `notifications`
 * shared prop, synced in app.ts); live changes arrive over Reverb and are
 * passed on to every open list through `onNotificationChange`.
 */
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

export type UserNotification = Domain.Notification.Data.UserNotificationData;
export type NotificationPreferences = Domain.Notification.Data.NotificationPreferencesData;
type NotificationSummary = Domain.Notification.Data.NotificationSummaryData;

// Notifications whose link opens the admin panel, which is not an Inertia page.
const ADMIN_PANEL_KINDS: ReadonlySet<Domain.Notification.Enums.NotificationKind> = new Set(['ai_model_unavailable']);

/** Opens the page a notification links to. */
export function visitNotification(notification: UserNotification): void {
    if (ADMIN_PANEL_KINDS.has(notification.kind)) {
        window.location.assign(notification.content.url);

        return;
    }

    router.visit(notification.content.url);
}

export const unreadCount = ref(0);
export const notificationPreferences = ref<NotificationPreferences | null>(null);

interface NotificationChangeListener {
    pushed?: (notification: UserNotification, isNew: boolean) => void;
    read?: (ids: string[] | null) => void;
    cleared?: () => void;
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

export function notificationsCleared(): void {
    setUnreadCount(0);

    listeners.forEach((listener) => listener.cleared?.());
}

// The tab title carries the unread count ("(3) Agents - Acme"), after the
// app's name (Settings → Platform). Inertia sets the title per page through
// pageTitle(); a change of count rewrites it.
let appName = 'ChatVia.ai';
let baseTitle: string | null = null;

/** Takes the app's name from each page the server sends (shared as `name`). */
export function setAppName(name: unknown): void {
    if (typeof name === 'string' && name !== '') appName = name;
}

function withUnreadCount(title: string): string {
    if (unreadCount.value <= 0) return title;

    return `(${unreadCount.value > 99 ? '99+' : unreadCount.value}) ${title}`;
}

export function pageTitle(title: string): string {
    // A page named after the app itself (the hosted chat's start) shows the name once.
    baseTitle = title === '' || title === appName ? appName : `${title} - ${appName}`;

    return withUnreadCount(baseTitle);
}

watch(unreadCount, () => {
    if (baseTitle !== null) document.title = withUnreadCount(baseTitle);
});
