<script lang="ts" setup>
// The in-app plan picker: monthly / yearly toggle, an optional discount banner
// and one card per plan. Selecting a plan subscribes, switches plan or opens
// the contact page (see usePlanSelection).
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BillingIntervalToggle from './components/BillingIntervalToggle.vue';
import DiscountBanner from './components/DiscountBanner.vue';
import PlanCard from './components/PlanCard.vue';
import PlanLlmsDialog from './components/PlanLlmsDialog.vue';
import { usePlanPricing } from './composables/usePlanPricing';
import { usePlanSelection } from './composables/usePlanSelection';
import { usePlanText } from './composables/usePlanText';
import type { PlanBillingConfig, PlanLlm, PlanPackage } from './types';

const props = defineProps<{
    packages: PlanPackage[];
    currentPackage: string;
    llms?: PlanLlm[];
    billingConfig: PlanBillingConfig;
}>();

const { t } = useI18n();
const { uiText } = usePlanText();

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
    billingConfig: () => props.billingConfig,
    isYearly,
});

// The "available models" dialog, opened from the first feature of the free
// (free models only) and starter (every model) plans.
const showLLMsModal = ref(false);
const currentLLMs = ref<PlanLlm[]>([]);

const handleFeatureClick = (feature: string, index: number, packageKey: string): void => {
    if (index === 0 && (packageKey === 'free' || packageKey === 'starter')) {
        if (feature.includes('fast')) {
            currentLLMs.value = (props.llms || []).filter((llm) => !llm.paid);
        } else {
            currentLLMs.value = props.llms || [];
        }
        showLLMsModal.value = true;
    }
};
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

                <BillingIntervalToggle v-model="isYearly" :savings-text="uiText('yearly_savings_text', billingConfig.ui.yearly_savings_text)" />

                <DiscountBanner v-if="isDiscountActive" :percentage="billingConfig.discount.percentage" :ends-at="billingConfig.discount.ends_at" />

                <!-- Packages Grid -->
                <div class="flex flex-wrap justify-center gap-8">
                    <PlanCard
                        v-for="(pkg, packageKey) in packages"
                        :key="pkg.id"
                        :pkg="pkg"
                        :package-key="String(packageKey)"
                        :billing-config="billingConfig"
                        :is-yearly="isYearly"
                        :is-current="isCurrentPackage(pkg.id)"
                        :is-subscribing="isSubscribing"
                        :subscribing-package="subscribingPackage"
                        :button-text="getButtonText(pkg.id)"
                        :feature-header="getFeatureHeader(pkg.id)"
                        @select="handlePackageSelection"
                        @feature-click="handleFeatureClick"
                    />
                </div>
            </div>
        </div>

        <PlanLlmsDialog v-model:open="showLLMsModal" :llms="currentLLMs" />
    </AppLayout>
</template>
