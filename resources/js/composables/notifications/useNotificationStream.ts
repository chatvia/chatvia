/**
 * Listens on the signed-in person's own channel for notifications: a new or
 * gathered one updates the bell and every open list (and may alert the
 * desktop or play a sound), and reads made in another tab clear them here.
 * Mount it once per page; the bell does.
 */
import type { SharedData } from '@/types';
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import { alertNewNotification, claimAlertLeadership } from './notificationAlerts';
import { notificationPushed, notificationsRead, type UserNotification } from './notificationStore';
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
    const page = usePage<SharedData>();
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
}
