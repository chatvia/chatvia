<script lang="ts" setup>
// The billing dashboard: trial / upgrade banners, the current plan with its
// add-ons, subscription management links and plan usage. The billing and usage
// data load after the page renders, and reload after any change made here.
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CreditCard, Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import AddonsSection from './components/AddonsSection.vue';
import CurrentPlanCard from './components/CurrentPlanCard.vue';
import SubscriptionManagementCard from './components/SubscriptionManagementCard.vue';
import TrialBanner from './components/TrialBanner.vue';
import UpgradeBanner from './components/UpgradeBanner.vue';
import UsageLimitsCard from './components/UsageLimitsCard.vue';
import { useBillingOverview } from './composables/useBillingOverview';
import { usePlanText } from './composables/usePlanText';

const { t } = useI18n();
const { planName } = usePlanText();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('billing.common.billing'),
        href: route('billing.index'),
    },
];

const { billingData, isLoadingBilling, usageData, isLoadingUsage, refreshAll } = useBillingOverview();
</script>

<template>
    <AppLayout :title="t('billing.index.page_title')" :breadcrumbs="breadcrumbs">
        <Head :title="t('billing.index.page_title')" />

        <div class="mx-auto w-full max-w-6xl px-4 py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                    <div>
                        <h1 class="flex items-center gap-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ t('billing.index.heading') }}</h1>
                        <p class="text-muted-foreground mt-2 flex items-center gap-2 text-sm sm:text-base">
                            {{ t('billing.index.subheading') }}
                        </p>
                    </div>
                </div>

                <!-- Stripe Payment Notice -->
                <div
                    class="flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50"
                >
                    <div class="text-muted-foreground flex items-center gap-2 text-sm">
                        <CreditCard class="h-4 w-4" />
                        <span>{{ t('billing.index.stripe_notice') }}</span>
                        <span class="font-semibold text-[#635BFF]">Stripe</span>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoadingBilling" class="flex items-center justify-center py-16">
                    <Loader2 class="text-muted-foreground h-8 w-8 animate-spin" />
                </div>

                <template v-else-if="billingData">
                    <TrialBanner
                        v-if="billingData.on_trial && !billingData.subscribed"
                        :plan-name="planName(billingData.current_package)"
                        :trial-ends-at="billingData.trial_ends_at"
                    />

                    <UpgradeBanner v-if="!billingData.subscribed && !billingData.on_trial" />

                    <div class="grid gap-8 md:grid-cols-3">
                        <!-- Current Plan + Add-ons (left column) -->
                        <div class="space-y-6 md:col-span-2">
                            <CurrentPlanCard :billing-data="billingData" @refresh="refreshAll" />

                            <AddonsSection :addons="billingData.addons" :subscribed="billingData.subscribed" @refresh="refreshAll" />
                        </div>

                        <!-- Usage & Limits -->
                        <div class="space-y-6">
                            <SubscriptionManagementCard :billing-data="billingData" @refresh="refreshAll" />

                            <UsageLimitsCard :usage-data="usageData" :is-loading="isLoadingUsage" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
