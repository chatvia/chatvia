/**
 * localStorage access that never throws.
 *
 * The hosted chat can run inside an iframe on another site, where the browser
 * may block storage (Safari, strict privacy settings, sandboxed iframes). Then
 * even touching `window.localStorage` throws a SecurityError, which at boot
 * would stop the whole app from mounting. These helpers turn a blocked or full
 * storage into "nothing saved" instead: reads return null and writes are
 * dropped, so the UI simply falls back to its defaults.
 */

function storage(): Storage | null {
    try {
        return typeof window !== 'undefined' ? window.localStorage : null;
    } catch {
        return null;
    }
}

export const safeStorage = {
    get(key: string): string | null {
        try {
            return storage()?.getItem(key) ?? null;
        } catch {
            return null;
        }
    },

    set(key: string, value: string): void {
        try {
            storage()?.setItem(key, value);
        } catch {
            // Blocked or over quota — the value stays in memory only.
        }
    },

    remove(key: string): void {
        try {
            storage()?.removeItem(key);
        } catch {
            // Blocked — nothing to remove.
        }
    },
};
