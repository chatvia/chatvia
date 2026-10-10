<script lang="ts" setup>
// The in-app plan picker: monthly / yearly toggle, an optional discount banner
// and one card per plan. Selecting a plan subscribes, switches plan or opens
// the contact page (see usePlanSelection).
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BillingIntervalToggle from './components/BillingIntervalToggle.vue';
import DiscountBanner from './components/DiscountBanner.vue';
import PlanCard from './components/PlanCard.vue';
import { usePlanPricing } from './composables/usePlanPricing';
import { usePlanSelection } from './composables/usePlanSelection';
import type { PlanBillingConfig, PlanPackage } from './types';

const props = defineProps<{
    packages: PlanPackage[];
    currentPackage: string;
    billingConfig: PlanBillingConfig;
}>();

const { t, n } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('billing.common.billing'),
        href: route('billing.index'),
    },
    {
        title: t('billing.plans.breadcrumb'),
        href: route('billing.packages'),
    },
];

const isYearly = ref(false);

const { isDiscountActive } = usePlanPricing(() => props.billingConfig, isYearly);

const { isSubscribing, subscribingPackage, isCurrentPackage, getButtonText, getFeatureHeader, handlePackageSelection } = usePlanSelection({
    packages: () => props.packages,
    currentPackage: () => props.currentPackage,
    isYearly,
});

// The badge on the yearly option, from what the plans save by paying yearly:
// "Save 20%" when every plan saves the same, "Save up to 25%" otherwise, and
// no badge when no plan saves anything.
const yearlySavingsText = computed<string | null>(() => {
    const percentages = Object.values(props.packages)
        .map((pkg) => pkg.monthly_savings.percentage)
        .filter((percentage) => percentage > 0);

    if (percentages.length === 0) return null;

    const best = Math.max(...percentages);
    const key = percentages.every((percentage) => percentage === best) ? 'billing.plans.save_percent' : 'billing.plans.save_up_to_percent';

    return t(key, { percent: n(best) });
});
</script>

<template>
    <AppLayout :title="t('billing.plans.page_title')" :breadcrumbs="breadcrumbs">
        <Head :title="t('billing.plans.page_title')" />

        <div class="container mx-auto max-w-7xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight">
                            <Sparkles class="text-primary h-8 w-8" />
                            {{ t('billing.plans.heading') }}
                        </h1>
                        <p class="text-muted-foreground">{{ t('billing.plans.subheading') }}</p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link :href="route('billing.index')">
                            <ArrowLeft class="me-2 h-4 w-4" />
                            {{ t('billing.common.back_to_billing') }}
                        </Link>
                    </Button>
                </div>

                <BillingIntervalToggle v-model="isYearly" :savings-text="yearlySavingsText" />

                <DiscountBanner v-if="isDiscountActive" :percentage="billingConfig.discount.percentage" :ends-at="billingConfig.discount.ends_at" />

                <!-- Packages Grid -->
                <div class="flex flex-wrap justify-center gap-8">
                    <PlanCard
                        v-for="pkg in packages"
                        :key="pkg.id"
                        :pkg="pkg"
                        :billing-config="billingConfig"
                        :is-yearly="isYearly"
                        :is-current="isCurrentPackage(pkg.id)"
                        :is-subscribing="isSubscribing"
                        :subscribing-package="subscribingPackage"
                        :button-text="getButtonText(pkg.id)"
                        :feature-header="getFeatureHeader(pkg.id)"
                        @select="handlePackageSelection"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
