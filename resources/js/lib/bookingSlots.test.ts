import { describe, expect, it } from 'vitest';
import { canGoBack, canGoForward, dayKey, groupSlotsByDay, shiftWindow } from './bookingSlots';

describe('groupSlotsByDay', () => {
    it('groups UTC times into the visitor day, in order', () => {
        const days = groupSlotsByDay(['2026-11-03T09:00:00Z', '2026-11-02T22:30:00Z', '2026-11-02T08:00:00Z'], 'Asia/Baghdad');

        // 22:30 UTC on the 2nd is 01:30 on the 3rd in Baghdad (UTC+3).
        expect(days).toEqual([
            { key: '2026-11-02', slots: ['2026-11-02T08:00:00Z'] },
            { key: '2026-11-03', slots: ['2026-11-02T22:30:00Z', '2026-11-03T09:00:00Z'] },
        ]);
    });

    it('returns no days for no times', () => {
        expect(groupSlotsByDay([], 'UTC')).toEqual([]);
    });
});

describe('dayKey', () => {
    it('formats as YYYY-MM-DD in the given zone', () => {
        expect(dayKey('2026-11-02T23:00:00Z', 'America/New_York')).toBe('2026-11-02');
        expect(dayKey('2026-11-02T23:00:00Z', 'Asia/Tokyo')).toBe('2026-11-03');
    });
});

describe('shiftWindow', () => {
    const earliest = new Date('2026-11-01T10:00:00Z');
    const first = { from: earliest, to: new Date('2026-11-08T10:00:00Z') };

    it('moves a week forward and back, never before the start', () => {
        const next = shiftWindow(first, 1, earliest, 31);
        expect(next.from.toISOString()).toBe('2026-11-08T10:00:00.000Z');
        expect(next.to.toISOString()).toBe('2026-11-15T10:00:00.000Z');

        expect(shiftWindow(next, -1, earliest, 31).from.toISOString()).toBe(earliest.toISOString());
        expect(shiftWindow(first, -1, earliest, 31).from.toISOString()).toBe(earliest.toISOString());
    });

    it('stops at the furthest day', () => {
        let window = first;
        for (let i = 0; i < 6; i++) window = shiftWindow(window, 1, earliest, 31);

        expect(window.to.toISOString()).toBe('2026-12-02T10:00:00.000Z');
        expect(canGoForward(window, earliest, 31)).toBe(false);
        expect(canGoBack(window, earliest)).toBe(true);
        expect(canGoBack(first, earliest)).toBe(false);
    });
});
