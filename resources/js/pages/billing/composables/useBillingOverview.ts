import { fetchBillingOverview, type BillingOverview } from '@/api/billing';
import { fetchUsage, type Usage } from '@/api/usage';
import { onMounted, ref } from 'vue';

/**
 * Loads the billing overview and the plan usage for the billing page once it
 * has mounted, and reloads both after a subscription or add-on change. A failed
 * load leaves the data empty so the page shows its "could not load" state.
 */
export function useBillingOverview() {
    const billingData = ref<BillingOverview | null>(null);
    const isLoadingBilling = ref(true);

    const usageData = ref<Usage | null>(null);
    const isLoadingUsage = ref(true);

    const fetchBillingData = async (): Promise<void> => {
        try {
            isLoadingBilling.value = true;
            billingData.value = await fetchBillingOverview();
        } catch (error) {
            console.error('Failed to fetch billing data:', error);
        } finally {
            isLoadingBilling.value = false;
        }
    };

    const fetchUsageData = async (): Promise<void> => {
        try {
            isLoadingUsage.value = true;
            usageData.value = await fetchUsage();
        } catch (error) {
            console.error('Failed to fetch usage data:', error);
        } finally {
            isLoadingUsage.value = false;
        }
    };

    const refreshAll = (): void => {
        fetchBillingData();
        fetchUsageData();
    };

    onMounted(refreshAll);

    return { billingData, isLoadingBilling, usageData, isLoadingUsage, refreshAll };
}
