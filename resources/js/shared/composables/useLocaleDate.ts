/**
 * Locale-aware date labels for charts and lists ("Jan 5", "Mon", "5"), in the
 * active vue-i18n language. For calendar dates in the user's preferred format
 * use `useDateFormat` instead.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const LOCALE_BCP47: Record<string, string> = {
    en: 'en',
    ar: 'ar',
    da: 'da',
    de: 'de',
    es: 'es',
    fa: 'fa',
    fr: 'fr',
    hi: 'hi',
    id: 'id',
    ja: 'ja',
    ko: 'ko',
    pt: 'pt',
    ru: 'ru',
    tr: 'tr',
    vi: 'vi',
    zh: 'zh',
};

/** Sunday 7 Jan 2024 — PostgreSQL DOW 0 and JS getDay() both use 0 = Sunday. */
const SUNDAY_ANCHOR = new Date(2024, 0, 7, 12);

function parseDateInput(input: string | Date): Date {
    if (input instanceof Date) {
        return input;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(input)) {
        return new Date(`${input}T12:00:00`);
    }

    return new Date(input);
}

export function useLocaleDate() {
    const { locale } = useI18n();

    const intlLocale = computed(() => LOCALE_BCP47[locale.value] ?? locale.value);

    const formatWeekdayShort = (input: string | Date): string => {
        return parseDateInput(input).toLocaleDateString(intlLocale.value, { weekday: 'short' });
    };

    const formatMonthDay = (input: string | Date): string => {
        return parseDateInput(input).toLocaleDateString(intlLocale.value, { month: 'short', day: 'numeric' });
    };

    const formatDayOfMonth = (input: string | Date): string => {
        return parseDateInput(input).toLocaleDateString(intlLocale.value, { day: 'numeric' });
    };

    const formatDayOfWeek = (dayNumber: number): string => {
        const date = new Date(SUNDAY_ANCHOR);
        date.setDate(SUNDAY_ANCHOR.getDate() + dayNumber);

        return formatWeekdayShort(date);
    };

    return {
        intlLocale,
        formatWeekdayShort,
        formatMonthDay,
        formatDayOfMonth,
        formatDayOfWeek,
    };
}
