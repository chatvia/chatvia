/**
 * Dates in the signed-in user's preferred format (Settings → Date & time),
 * formatted with dayjs: `useDateFormat()` inside components, the pure
 * `formatDateWith()` where an explicit format is already known.
 */
import { usePage } from '@inertiajs/vue3';
import dayjs from 'dayjs';

/**
 * A PHP `date()` format string, as stored in `users.date_format` (one of the
 * values of App\Identity\Workspace\Enums\DateFormat).
 */
export type DateFormatString = string;

export const DEFAULT_DATE_FORMAT: DateFormatString = 'Y-m-d H:i:s';

/** PHP format characters and their dayjs equivalents. */
const PHP_TO_DAYJS: Record<string, string> = {
    Y: 'YYYY',
    y: 'YY',
    m: 'MM',
    n: 'M',
    M: 'MMM',
    F: 'MMMM',
    d: 'DD',
    j: 'D',
    D: 'ddd',
    l: 'dddd',
    H: 'HH',
    G: 'H',
    h: 'hh',
    g: 'h',
    i: 'mm',
    s: 'ss',
    A: 'A',
    a: 'a',
};

/** PHP characters that print part of the time, where the date-only form is cut. */
const TIME_CHARACTERS = new Set(['H', 'G', 'h', 'g', 'i', 's', 'A', 'a']);

/** Separators dayjs prints as they are; any other literal is bracket-escaped. */
const PLAIN_SEPARATORS = new Set([' ', '-', '/', '.', ':', ',']);

type DateInput = string | number | Date | null | undefined;

/** Translates a PHP format string to dayjs tokens; `\x` escapes stay literal. */
function toDayjs(format: DateFormatString): string {
    let tokens = '';

    for (let index = 0; index < format.length; index++) {
        const character = format[index];

        if (character === '\\' && index + 1 < format.length) {
            tokens += `[${format[++index]}]`;
        } else if (character in PHP_TO_DAYJS) {
            tokens += PHP_TO_DAYJS[character];
        } else if (PLAIN_SEPARATORS.has(character)) {
            tokens += character;
        } else {
            tokens += `[${character}]`;
        }
    }

    return tokens;
}

/** The date part of a format: everything before its first time character. */
function datePart(format: DateFormatString): DateFormatString {
    const cut = [...format].findIndex((character, index) => TIME_CHARACTERS.has(character) && format[index - 1] !== '\\');

    return cut === -1 ? format : format.slice(0, cut).replace(/[\s,:]+$/, '');
}

/**
 * Format a date with an explicit PHP format string — pure, no Inertia access.
 * Returns '' for empty/invalid input.
 */
export function formatDateWith(format: DateFormatString, value: DateInput): string {
    if (value === null || value === undefined || value === '') return '';
    const date = dayjs(value);
    return date.isValid() ? date.format(toDayjs(format || DEFAULT_DATE_FORMAT)) : '';
}

/**
 * Reads the authenticated user's preferred format from the shared Inertia
 * props and returns dayjs-backed formatters bound to it. The single place the
 * dashboard should reach for to render a date — see Settings → Date & time.
 *
 * Falls back to ISO when there is no auth user (widget / embed) or the pref is
 * unset.
 */
export function useDateFormat() {
    const page = usePage();

    const format = (): DateFormatString => page.props.auth?.user?.date_format || DEFAULT_DATE_FORMAT;

    /** The calendar date alone: the preferred format without its time. */
    const formatDate = (value: DateInput): string => formatDateWith(datePart(format()), value);

    /** The full preferred format, date and time. */
    const formatDateTime = (value: DateInput): string => formatDateWith(format(), value);

    return { formatDate, formatDateTime, dateFormat: format };
}
