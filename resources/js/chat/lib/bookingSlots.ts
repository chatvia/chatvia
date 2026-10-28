/**
 * Pure helpers for the chat's booking calendar: grouping the free times the
 * server sends (UTC) into the visitor's own days, and the week window the
 * calendar may browse to. Kept free of Vue so it can be unit-tested.
 */

const DAY_MS = 24 * 60 * 60 * 1000;

export interface SlotDay {
    /** The day in the visitor's time zone, `YYYY-MM-DD`. */
    key: string;
    /** The free start times on that day, ISO UTC, earliest first. */
    slots: string[];
}

/** `YYYY-MM-DD` of an instant in a time zone. */
export function dayKey(iso: string, timeZone: string): string {
    // en-CA formats dates as YYYY-MM-DD.
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
}

/** The free times grouped by the visitor's day, days and times in order; days without times are left out. */
export function groupSlotsByDay(slots: string[], timeZone: string): SlotDay[] {
    const days = new Map<string, string[]>();

    for (const slot of [...slots].sort((a, b) => Date.parse(a) - Date.parse(b))) {
        const key = dayKey(slot, timeZone);
        days.set(key, [...(days.get(key) ?? []), slot]);
    }

    return [...days.entries()].map(([key, daySlots]) => ({ key, slots: daySlots }));
}

export interface BookingWindow {
    from: Date;
    to: Date;
}

/** The week after (or before) `current`, kept between `earliest` and `earliest + maxDaysAhead`. */
export function shiftWindow(current: BookingWindow, direction: 1 | -1, earliest: Date, maxDaysAhead: number, windowDays = 7): BookingWindow {
    const latest = earliest.getTime() + maxDaysAhead * DAY_MS;
    const from = Math.min(Math.max(current.from.getTime() + direction * windowDays * DAY_MS, earliest.getTime()), latest);
    const to = Math.min(from + windowDays * DAY_MS, latest);

    return { from: new Date(from), to: new Date(to) };
}

export function canGoBack(current: BookingWindow, earliest: Date): boolean {
    return current.from.getTime() > earliest.getTime() + 60 * 1000;
}

export function canGoForward(current: BookingWindow, earliest: Date, maxDaysAhead: number): boolean {
    return current.to.getTime() < earliest.getTime() + maxDaysAhead * DAY_MS - 60 * 1000;
}

/** The visitor's IANA time zone, UTC when the browser does not say. */
export function visitorTimeZone(): string {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}
