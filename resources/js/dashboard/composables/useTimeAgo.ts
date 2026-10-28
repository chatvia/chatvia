/**
 * Short relative times ("5 minutes ago") in the active UI language, built on
 * `Intl.RelativeTimeFormat`. Counts whole minutes, then hours, then days, and
 * shows "Just now" for anything under a minute (or a missing timestamp).
 */
import { useLocaleDate } from '@/shared/composables/useLocaleDate';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const MS_PER_MINUTE = 60_000;

export function useTimeAgo() {
    const { t } = useI18n();
    const { intlLocale } = useLocaleDate();

    // `numeric: 'always'` keeps "1 day ago" instead of "yesterday", matching the counts above.
    const formatter = computed(() => new Intl.RelativeTimeFormat(intlLocale.value, { numeric: 'always' }));

    const formatTimeAgo = (timestamp: string | null | undefined): string => {
        if (!timestamp) return t('shared.time_ago.just_now');

        const diffInMinutes = Math.floor((Date.now() - new Date(timestamp).getTime()) / MS_PER_MINUTE);
        if (!Number.isFinite(diffInMinutes) || diffInMinutes < 1) return t('shared.time_ago.just_now');
        if (diffInMinutes < 60) return formatter.value.format(-diffInMinutes, 'minute');

        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) return formatter.value.format(-diffInHours, 'hour');

        return formatter.value.format(-Math.floor(diffInHours / 24), 'day');
    };

    return {
        formatTimeAgo,
    };
}
