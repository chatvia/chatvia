import { afterEach, describe, expect, it, vi } from 'vitest';
import { safeStorage } from './safeStorage';

// In an iframe on another site the browser may block storage, and touching
// window.localStorage then throws. safeStorage must never throw: the chat
// would not even mount.
function memoryStorage(): Storage {
    const values = new Map<string, string>();

    return {
        get length() {
            return values.size;
        },
        clear: () => values.clear(),
        getItem: (key) => values.get(key) ?? null,
        key: (index) => [...values.keys()][index] ?? null,
        removeItem: (key) => void values.delete(key),
        setItem: (key, value) => void values.set(key, value),
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('safeStorage', () => {
    it('reads, writes and removes through localStorage when it is there', () => {
        vi.stubGlobal('window', { localStorage: memoryStorage() });

        safeStorage.set('theme', 'dark');
        expect(safeStorage.get('theme')).toBe('dark');

        safeStorage.remove('theme');
        expect(safeStorage.get('theme')).toBeNull();
    });

    it('reads nothing and drops writes when the browser blocks storage', () => {
        vi.stubGlobal('window', {
            get localStorage(): Storage {
                throw new DOMException('The operation is insecure.', 'SecurityError');
            },
        });

        expect(() => safeStorage.set('theme', 'dark')).not.toThrow();
        expect(() => safeStorage.remove('theme')).not.toThrow();
        expect(safeStorage.get('theme')).toBeNull();
    });

    it('drops a write that is over the quota', () => {
        const full = memoryStorage();
        full.setItem = () => {
            throw new DOMException('Quota exceeded', 'QuotaExceededError');
        };
        vi.stubGlobal('window', { localStorage: full });

        expect(() => safeStorage.set('draft', 'x'.repeat(10))).not.toThrow();
        expect(safeStorage.get('draft')).toBeNull();
    });

    it('works where there is no window at all, as during a server render', () => {
        vi.stubGlobal('window', undefined);

        expect(safeStorage.get('theme')).toBeNull();
        expect(() => safeStorage.set('theme', 'dark')).not.toThrow();
    });
});
