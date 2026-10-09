import { useDateFormat } from '@/composables/useDateFormat';
import { useI18n } from 'vue-i18n';

/**
 * Formats the Stripe timestamps on the billing page (period end, cancellation
 * date, scheduled plan change) in the user's preferred date format. Stripe
 * values arrive as seconds or milliseconds, sometimes as strings; anything
 * empty or unreadable shows "N/A".
 */
export function useBillingTimestamp() {
    const { t } = useI18n();
    const { formatDate } = useDateFormat();

    const formatTimestamp = (timestamp: number | string | null | undefined): string => {
        if (!timestamp || timestamp === 0) return t('billing.common.not_available');

        let ts: number;

        if (typeof timestamp === 'string') {
            ts = parseInt(timestamp);
            if (isNaN(ts)) return t('billing.common.not_available');
        } else {
            ts = timestamp;
        }

        // Values above 10^12 are already milliseconds; smaller ones are seconds.
        const date = new Date(ts > 1000000000000 ? ts : ts * 1000);

        if (isNaN(date.getTime())) return t('billing.common.not_available');

        return formatDate(date);
    };

    return { formatTimestamp };
}
