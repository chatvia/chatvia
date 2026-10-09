<script lang="ts" setup>
// The workspace's current plan on the billing page: price, subscription
// status, trial / renewal dates, a cancelled-subscription notice with a resume
// button, any scheduled plan change, and the plan's feature list.
import type { BillingOverview } from '@/api/billing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, Calendar, CheckCircle, ChevronDown, Clock, Package, Star } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBillingTimestamp } from '../composables/useBillingTimestamp';
import { usePlanText } from '../composables/usePlanText';
import PendingPlanChangeNotice from './PendingPlanChangeNotice.vue';

const props = defineProps<{
    billingData: BillingOverview;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();
const { planName, packageDescription, featureLabel } = usePlanText();
const { formatTimestamp } = useBillingTimestamp();

const resumeForm = useForm({});

const resumeSubscription = (): void => {
    resumeForm.post(route('billing.resume'), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'trialing':
            return 'secondary';
        case 'canceled':
        case 'incomplete':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'active':
            return t('billing.index.status.active');
        case 'trialing':
            return t('billing.index.status.trial');
        case 'canceled':
            return t('billing.index.status.cancelled');
        case 'incomplete':
            return t('billing.index.status.incomplete');
        case 'past_due':
            return t('billing.index.status.past_due');
        default:
            return status;
    }
};

const isYearly = computed<boolean>(() => props.billingData.current_package?.is_yearly ?? false);

const currentFeatures = computed(() => {
    if (!props.billingData.current_package?.features) return [];
    return isYearly.value ? props.billingData.current_package.features.yearly : props.billingData.current_package.features.monthly;
});

const planFeatureLabel = (key: string, text: string): string => featureLabel(props.billingData.current_package?.id, isYearly.value, key, text);

// Higher tiers list ~17 features. Show the first 3 so the card stays scannable
// and let the user expand the rest. `features` may arrive as an array or a
// numeric-keyed object, so read it through Object.entries either way.
const FEATURES_PREVIEW_COUNT = 3;
const featuresExpanded = ref(false);
const featuresList = computed<[string, string][]>(() => Object.entries(currentFeatures.value ?? {}) as [string, string][]);
const visibleFeatures = computed<[string, string][]>(() =>
    featuresExpanded.value ? featuresList.value : featuresList.value.slice(0, FEATURES_PREVIEW_COUNT),
);
const hiddenFeaturesCount = computed(() => Math.max(0, featuresList.value.length - FEATURES_PREVIEW_COUNT));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-3">
                <Package class="text-primary h-6 w-6" />
                {{ t('billing.index.current_plan.title') }}
            </CardTitle>
            <CardDescription>{{ t('billing.index.current_plan.description') }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <PendingPlanChangeNotice v-if="billingData.pending_plan_change" :change="billingData.pending_plan_change" @refresh="emit('refresh')" />

            <!-- Plan Info -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-lg">
                        <Star class="text-primary h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold">{{ planName(billingData.current_package) }}</h3>
                        <p class="text-muted-foreground">{{ packageDescription(billingData.current_package) }}</p>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-2xl font-bold">
                        {{ isYearly ? billingData.current_package.yearly_price_formatted : billingData.current_package.monthly_price_formatted }}

                        <span class="text-muted-foreground text-sm font-normal">
                            /{{ isYearly ? t('billing.index.per_year') : t('billing.index.per_month') }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Subscription Status -->
            <div v-if="billingData.subscribed || billingData.on_trial" class="space-y-4">
                <Separator />

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <CheckCircle v-if="billingData.subscribed" class="h-5 w-5 text-green-500" />
                        <AlertTriangle v-else class="h-5 w-5 text-orange-500" />
                        <span class="font-medium">{{ t('billing.index.status_label') }}</span>
                    </div>
                    <Badge :variant="getStatusBadgeVariant(billingData.subscription?.stripe_status || 'trialing')">
                        {{ getStatusLabel(billingData.subscription?.stripe_status || 'trialing') }}
                    </Badge>
                </div>

                <!-- Trial Information -->
                <div v-if="billingData.on_trial && billingData.trial_ends_at" class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Clock class="h-5 w-5 text-blue-500" />
                        <span class="font-medium">{{ t('billing.index.trial_ends') }}</span>
                    </div>
                    <span class="text-sm">
                        {{ billingData.trial_ends_at }}
                    </span>
                </div>

                <!-- Billing Period -->
                <div v-if="billingData.subscription && !billingData.on_trial" class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Calendar class="h-5 w-5 text-blue-500" />
                        <span class="font-medium">
                            {{ billingData.subscription_canceled ? t('billing.index.access_ends') : t('billing.index.next_billing') }}
                        </span>
                    </div>
                    <span class="text-sm">
                        {{ formatTimestamp(billingData.subscription.current_period_end) }}
                    </span>
                </div>

                <!-- Cancellation Notice -->
                <div
                    v-if="billingData.subscription_canceled && billingData.subscription"
                    class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950/20"
                >
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="mt-0.5 h-5 w-5 text-orange-500" />
                        <div>
                            <p class="font-medium text-orange-800 dark:text-orange-200">
                                {{ t('billing.index.cancelled.title') }}
                            </p>
                            <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                {{ t('billing.index.cancelled.description', { date: formatTimestamp(billingData.subscription.ends_at) }) }}
                            </p>
                            <Button @click="resumeSubscription" :disabled="resumeForm.processing" size="sm" class="mt-3">
                                {{ t('billing.index.cancelled.resume') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plan Features -->
            <div class="space-y-4">
                <Separator />
                <h4 class="font-semibold">{{ t('billing.index.plan_features') }}</h4>
                <div class="grid gap-2">
                    <div v-for="[key, feature] in visibleFeatures" :key="key" class="flex items-center gap-2">
                        <CheckCircle class="h-4 w-4 text-green-500" />
                        <span class="text-sm">{{ planFeatureLabel(key, feature) }}</span>
                    </div>
                </div>
                <button
                    v-if="hiddenFeaturesCount > 0"
                    type="button"
                    class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs transition-colors"
                    @click="featuresExpanded = !featuresExpanded"
                >
                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': featuresExpanded }" />
                    {{ featuresExpanded ? t('billing.index.show_less') : t('billing.index.show_more', { count: hiddenFeaturesCount }) }}
                </button>
            </div>
        </CardContent>
    </Card>
</template>
