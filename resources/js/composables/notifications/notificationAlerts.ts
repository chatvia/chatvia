/**
 * Desktop alerts and the notification chime. Only one open dashboard tab
 * plays them (the one holding a Web Locks lock; the next tab takes over when
 * it closes), so five open tabs make one sound, not five. Desktop alerts are
 * also tagged with the notification id, which the browser shows once.
 *
 * The chime is made with Web Audio, so there is no sound file to load.
 * Browsers only allow sound after the page was used, so the audio is
 * unlocked on the first click or key press.
 */
import { notificationPreferences, visitNotification, type UserNotification } from './notificationStore';
import type { NotificationText } from './useNotificationText';

const LOCK_NAME = 'chatvia-notification-alerts';

let leading = false;
let leadershipClaimed = false;
let audio: AudioContext | null = null;

export function desktopAlertsSupported(): boolean {
    return typeof window !== 'undefined' && 'Notification' in window;
}

export function desktopPermission(): NotificationPermission | 'unsupported' {
    return desktopAlertsSupported() ? window.Notification.permission : 'unsupported';
}

export async function requestDesktopPermission(): Promise<NotificationPermission | 'unsupported'> {
    if (!desktopAlertsSupported()) return 'unsupported';

    return window.Notification.requestPermission();
}

/** Called once per tab: queue for the lock and keep it for the tab's life once given. */
export function claimAlertLeadership(): void {
    if (leadershipClaimed) return;
    leadershipClaimed = true;

    if ('locks' in navigator) {
        void navigator.locks.request(LOCK_NAME, () => {
            leading = true;

            return new Promise<void>(() => {});
        });
    } else {
        leading = true;
    }

    const unlock = () => {
        void audioContext()?.resume();
        window.removeEventListener('pointerdown', unlock);
        window.removeEventListener('keydown', unlock);
    };
    window.addEventListener('pointerdown', unlock);
    window.addEventListener('keydown', unlock);
}

function audioContext(): AudioContext | null {
    if (audio === null && typeof window !== 'undefined' && 'AudioContext' in window) {
        audio = new AudioContext();
    }

    return audio;
}

/** Two short rising notes. */
export function playChime(): void {
    const context = audioContext();
    if (!context) return;
    if (context.state === 'suspended') void context.resume();

    const start = context.currentTime;
    [
        [659.25, 0],
        [880, 0.14],
    ].forEach(([frequency, offset]) => {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start + offset);
        gain.gain.exponentialRampToValueAtTime(0.2, start + offset + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.4);
        oscillator.connect(gain).connect(context.destination);
        oscillator.start(start + offset);
        oscillator.stop(start + offset + 0.45);
    });
}

function showDesktopAlert(notification: UserNotification, text: NotificationText): void {
    if (desktopPermission() !== 'granted') return;
    // The person is looking at this tab: the bell is enough.
    if (document.visibilityState === 'visible' && document.hasFocus()) return;

    const alert = new window.Notification(text.title, {
        body: text.body,
        tag: notification.id,
        icon: '/favicon.ico',
    });

    alert.onclick = () => {
        window.focus();
        visitNotification(notification);
        alert.close();
    };
}

/** A new notification arrived: alert and chime as the person chose for its category. */
export function alertNewNotification(notification: UserNotification, text: NotificationText): void {
    if (!leading) return;

    const preference = notificationPreferences.value?.[notification.category];
    if (!preference?.bell) return;

    if (preference.desktop) showDesktopAlert(notification, text);
    if (preference.sound) playChime();
}
