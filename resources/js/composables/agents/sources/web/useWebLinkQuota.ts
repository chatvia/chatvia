/**
 * The plan's link quota for website training: how many links are used and
 * left, plus the warning shown when the quota runs low (80 % and up) or out.
 * `linkUsage` stays null until the usage endpoint answers.
 */
import { fetchUsage, type UsageStats } from '@/api/usage';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

export interface LinkLimitWarning {
    type: 'critical' | 'warning';
    message: string;
}

export function useWebLinkQuota() {
    const { t } = useI18n();

    const linkUsage = ref<UsageStats | null>(null);

    async function refreshUsage(): Promise<void> {
        try {
            linkUsage.value = (await fetchUsage()).links;
        } catch {
            // The quota only drives hints and limits; the page works without it.
        }
    }

    const limitWarning = computed<LinkLimitWarning | null>(() => {
        if (!linkUsage.value) return null;

        const { limit, used, remaining } = linkUsage.value;
        const usagePercentage = (used / limit) * 100;
        const percentage = usagePercentage.toFixed(1);

        if (remaining === 0) {
            return { type: 'critical', message: t('agents.sources.web.limit_all_used', { limit }) };
        }
        if (usagePercentage >= 95) {
            return { type: 'critical', message: t('agents.sources.web.limit_critical', { used, limit, percentage, remaining }) };
        }
        if (usagePercentage >= 80) {
            return { type: 'warning', message: t('agents.sources.web.limit_warning', { used, limit, percentage, remaining }) };
        }

        return null;
    });

    return { linkUsage, refreshUsage, limitWarning };
}

export type WebLinkQuota = ReturnType<typeof useWebLinkQuota>;
