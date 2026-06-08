import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import dayjs from 'dayjs';

/**
 * Keys must stay in lock-step with Support\Enums\User\DateFormat on the server.
 */
export type DateFormatKey = 'iso' | 'dmy_slash' | 'mdy_slash' | 'dmy_dot' | 'long';

const FALLBACK: DateFormatKey = 'iso';

/**
 * dayjs format tokens for each preset. The server stores the enum *key* (the
 * single source of truth); this maps that key to a dayjs token, mirroring the
 * PHP tokens in Support\Enums\User\DateFormat::phpFormat().
 */
const DATE_TOKENS: Record<DateFormatKey, string> = {
    iso: 'YYYY-MM-DD',
    dmy_slash: 'DD/MM/YYYY',
    mdy_slash: 'MM/DD/YYYY',
    dmy_dot: 'DD.MM.YYYY',
    long: 'MMM D, YYYY',
};

type DateInput = string | number | Date | null | undefined;

function dateToken(key: DateFormatKey): string {
    return DATE_TOKENS[key] ?? DATE_TOKENS[FALLBACK];
}

/**
 * Format a date with an explicit key — pure, no Inertia access. Mirrors the PHP
 * tokens in Support\Enums\User\DateFormat. Returns '' for empty/invalid input.
 */
export function formatDateWith(key: DateFormatKey, value: DateInput): string {
    if (value === null || value === undefined || value === '') return '';
    const date = dayjs(value);
    return date.isValid() ? date.format(dateToken(key)) : '';
}

/**
 * Reads the authenticated user's preferred date format from the shared Inertia
 * props and returns dayjs-backed formatters bound to it. The single place the
 * dashboard should reach for to render a calendar date — see Settings → Date &
 * time.
 *
 * Falls back to ISO when there is no auth user (widget / embed) or the pref is
 * unset.
 */
export function useDateFormat() {
    const page = usePage<SharedData>();

    const key = (): DateFormatKey => (page.props.auth?.user?.date_format as DateFormatKey) || FALLBACK;

    const formatDate = (value: DateInput): string => formatDateWith(key(), value);

    /**
     * Preferred date + a fixed 24h HH:mm time. Only the date portion is
     * user-configurable for now; the time format is intentionally constant.
     */
    const formatDateTime = (value: DateInput): string => {
        if (value === null || value === undefined || value === '') return '';
        const date = dayjs(value);
        return date.isValid() ? date.format(`${dateToken(key())} HH:mm`) : '';
    };

    return { formatDate, formatDateTime, dateFormatKey: key };
}
