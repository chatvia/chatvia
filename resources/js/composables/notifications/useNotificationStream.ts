/**
 * Listens on the signed-in person's own channel for notifications: a new or
 * gathered one updates the bell and every open list (and may alert the
 * desktop or play a sound), and reads or a "Clear all" made in another tab
 * show here too.
 * Mount it once per page; the bell does.
 */
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { alertNewNotification, claimAlertLeadership } from './notificationAlerts';
import { notificationPushed, notificationsCleared, notificationsRead, type UserNotification } from './notificationStore';
import { useNotificationText } from './useNotificationText';

interface PushedPayload {
    notification: UserNotification;
    is_new: boolean;
}

interface ReadPayload {
    ids: string[] | null;
    unread: number;
}

export function useNotificationStream(): void {
    const page = usePage();
    const userId = page.props.auth?.user?.id;
    if (!userId) return;

    const { describe } = useNotificationText();
    const channel = `App.Identity.Workspace.Models.User.${userId}`;

    claimAlertLeadership();

    useEcho<PushedPayload>(channel, '.UserNotificationPushed', (event) => {
        notificationPushed(event.notification, event.is_new);
        if (event.is_new) alertNewNotification(event.notification, describe(event.notification));
    });

    useEcho<ReadPayload>(channel, '.UserNotificationsRead', (event) => {
        notificationsRead(event.ids, event.unread);
    });

    useEcho(channel, '.UserNotificationsCleared', () => {
        notificationsCleared();
    });
}
