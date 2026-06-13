<script lang="ts" setup>
import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useCatalogText } from '@/composables/useCatalogText';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bot,
    Calendar,
    CheckCircle,
    ChevronDown,
    Clock,
    CreditCard,
    CreditCardIcon,
    FileText,
    Link2,
    Loader2,
    MessageSquare,
    Package,
    Receipt,
    Star,
    Timer,
    Trash2,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AddonsSection from './components/AddonsSection.vue';

const { t } = useI18n();
const { catalogText } = useCatalogText();

// Plan name/description come from config/billing.php; overlay the Arabic
// translation by plan id, falling back to the English the server sent.
const planName = (pkg: { id: string; name: string }): string => catalogText(`billing.catalog.packages.${pkg.id}.name`, pkg.name);
const packageDescription = (pkg: { id: string; description: string }): string =>
    catalogText(`billing.catalog.packages.${pkg.id}.description`, pkg.description);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('billing.common.billing'),
        href: route('billing.index'),
    },
];

const cancelForm = useForm({
    immediately: false as boolean,
});

const resumeForm = useForm({});

const cancelScheduleForm = useForm({});

// Billing data
const billingData = ref<Domain.Billing.Data.Resources.Subscription.BillingResourceData | null>(null);
const isLoadingBilling = ref(true);

// Usage data
const usageData = ref<App.Identity.Workspace.Data.Resources.My.Usage.UsageResourceData | null>(null);
const isLoadingUsage = ref(true);

const fetchBillingData = async () => {
    try {
        isLoadingBilling.value = true;
        const response = await fetch(route('billing.data'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            billingData.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to fetch billing data:', error);
    } finally {
        isLoadingBilling.value = false;
    }
};

const fetchUsageData = async () => {
    try {
        isLoadingUsage.value = true;
        const response = await fetch(route('usage'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            usageData.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to fetch usage data:', error);
    } finally {
        isLoadingUsage.value = false;
    }
};

const getUsagePercentage = (used: number, limit: number) => {
    if (limit === 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
};

const getUsageColor = (percentage: number) => {
    if (percentage >= 90) return 'bg-red-500';
    if (percentage >= 75) return 'bg-orange-500';
    if (percentage >= 50) return 'bg-yellow-500';
    return 'bg-green-500';
};

onMounted(() => {
    fetchBillingData();
    fetchUsageData();
});

const formatDate = (timestamp: number | string | null | undefined) => {
    if (!timestamp || timestamp === 0) return t('billing.common.not_available');

    let ts: number;

    // Convert to number if it's a string
    if (typeof timestamp === 'string') {
        ts = parseInt(timestamp);
        if (isNaN(ts)) return t('billing.common.not_available');
    } else {
        ts = timestamp;
    }

    // Handle both seconds and milliseconds timestamps
    const date = new Date(ts > 1000000000000 ? ts : ts * 1000);

    // Check if date is valid
    if (isNaN(date.getTime())) return t('billing.common.not_available');

    return date.toDateString();
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

const getStatusLabel = (status: string) => {
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

// Determine if the current package is yearly
const isYearly = computed<boolean>(() => {
    return billingData.value?.current_package?.is_yearly ?? false;
});

const currentFeatures = computed(() => {
    if (!billingData.value?.current_package?.features) return [];
    return isYearly.value ? billingData.value.current_package.features.yearly : billingData.value.current_package.features.monthly;
});

// Translate a current-package feature bullet by its stable key (messages, agents,
// …), falling back to the English the server sent. Keyed by plan id + cycle.
const planFeatureLabel = (key: string, text: string): string =>
    catalogText(`billing.catalog.packages.${billingData.value?.current_package?.id}.features.${isYearly.value ? 'yearly' : 'monthly'}.${key}`, text);

// Plan feature lists are long (~17 entries on the higher tiers). Show the
// first 3 by default so the billing card stays scannable and let the user
// expand to see the rest. The DTO types `features` as a union of array /
// numeric-keyed object, so coerce to a plain array before slicing.
const FEATURES_PREVIEW_COUNT = 3;
const featuresExpanded = ref(false);
const featuresList = computed<[string, string][]>(() => Object.entries(currentFeatures.value ?? {}) as [string, string][]);
const visibleFeatures = computed<[string, string][]>(() =>
    featuresExpanded.value ? featuresList.value : featuresList.value.slice(0, FEATURES_PREVIEW_COUNT),
);
const hiddenFeaturesCount = computed(() => Math.max(0, featuresList.value.length - FEATURES_PREVIEW_COUNT));

const refreshAll = () => {
    fetchBillingData();
    fetchUsageData();
};

const cancelSubscription = (immediately = false) => {
    cancelForm.immediately = immediately;
    cancelForm.post(route('billing.cancel'), {
        preserveScroll: true,
        onSuccess: refreshAll,
    });
};

const resumeSubscription = () => {
    resumeForm.post(route('billing.resume'), {
        preserveScroll: true,
        onSuccess: refreshAll,
    });
};

const cancelScheduledPlanChange = () => {
    cancelScheduleForm.post(route('billing.schedule.cancel'), {
        preserveScroll: true,
        onSuccess: refreshAll,
    });
};
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
                    <!-- Trial Information Banner for Trial Users -->
                    <div v-if="billingData.on_trial && !billingData.subscribed" class="mb-8">
                        <Card
                            class="relative overflow-hidden border-emerald-200 bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 dark:border-emerald-800 dark:from-emerald-950/30 dark:via-teal-950/30 dark:to-cyan-950/30"
                        >
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-emerald-400/10 via-teal-400/10 to-cyan-400/10 opacity-70 dark:from-emerald-400/5 dark:via-teal-400/5 dark:to-cyan-400/5"
                            ></div>
                            <CardContent class="relative z-10 p-6">
                                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 shadow-sm dark:from-emerald-400 dark:to-teal-500"
                                        >
                                            <Zap class="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <h3
                                                class="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text font-semibold text-transparent dark:from-emerald-400 dark:to-teal-400"
                                            >
                                                {{ t('billing.index.trial.heading') }}
                                            </h3>
                                            <p class="mt-1 text-sm text-emerald-600/70 dark:text-emerald-300/70">
                                                {{ t('billing.index.trial.ends_on', { plan: planName(billingData.current_package) }) }}
                                                <span class="font-semibold">{{ billingData.trial_ends_at }}</span>
                                            </p>
                                            <p class="mt-1 text-sm text-emerald-600/70 dark:text-emerald-300/70">
                                                {{ t('billing.index.trial.auto_start', { date: billingData.trial_ends_at }) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <Badge variant="outline" class="w-fit border-emerald-500 text-emerald-700 dark:text-emerald-300">
                                            {{ t('billing.index.trial.badge') }}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Simple Subscription Encouragement for Non-Subscribed Users -->
                    <div v-if="!billingData.subscribed && !billingData.on_trial" class="mb-8">
                        <Card
                            class="relative overflow-hidden border-indigo-200 bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:border-indigo-800 dark:from-indigo-950/30 dark:via-purple-950/30 dark:to-pink-950/30"
                        >
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-indigo-400/10 via-purple-400/10 to-pink-400/10 opacity-70 dark:from-indigo-400/5 dark:via-purple-400/5 dark:to-pink-400/5"
                            ></div>
                            <div
                                class="absolute -inset-[100%] animate-[spin_8s_linear_infinite] bg-gradient-to-r from-transparent via-indigo-400/20 to-transparent dark:via-indigo-400/10"
                            ></div>
                            <CardContent class="relative z-10 p-6">
                                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 shadow-sm dark:from-indigo-400 dark:to-purple-500"
                                        >
                                            <Zap class="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <h3
                                                class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text font-semibold text-transparent dark:from-indigo-400 dark:to-purple-400"
                                            >
                                                {{ t('billing.index.upgrade.heading') }}
                                            </h3>
                                            <p class="text-sm text-indigo-600/70 dark:text-indigo-300/70">
                                                {{ t('billing.index.upgrade.description') }}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        class="gap-2 border-0 bg-gradient-to-r from-indigo-600 to-purple-600 whitespace-nowrap text-white shadow-sm hover:from-indigo-700 hover:to-purple-700"
                                        as-child
                                    >
                                        <Link :href="route('billing.packages')">
                                            <Star class="h-4 w-4" />
                                            {{ t('billing.common.view_plans') }}
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid gap-8 md:grid-cols-3">
                        <!-- Current Plan + Add-ons (left column) -->
                        <div class="space-y-6 md:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-3">
                                        <Package class="text-primary h-6 w-6" />
                                        {{ t('billing.index.current_plan.title') }}
                                    </CardTitle>
                                    <CardDescription>{{ t('billing.index.current_plan.description') }}</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <!-- Pending Plan Change Notice -->
                                    <div
                                        v-if="billingData.pending_plan_change"
                                        class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20"
                                    >
                                        <div class="flex items-start gap-3">
                                            <Calendar class="mt-0.5 h-5 w-5 text-blue-500" />
                                            <div>
                                                <h4 class="font-medium text-blue-800 dark:text-blue-200">
                                                    {{ t('billing.index.pending_change.heading') }}
                                                </h4>
                                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                                    <template
                                                        v-if="
                                                            billingData.pending_plan_change.from_package && billingData.pending_plan_change.to_package
                                                        "
                                                    >
                                                        {{ t('billing.index.pending_change.change_from') }}
                                                        <span class="font-medium">{{ billingData.pending_plan_change.from_package }}</span>
                                                        {{ t('billing.index.pending_change.to') }}
                                                        <span class="font-medium">{{ billingData.pending_plan_change.to_package }}</span>
                                                        {{
                                                            billingData.pending_plan_change.is_yearly
                                                                ? t('billing.index.pending_change.cycle_yearly')
                                                                : t('billing.index.pending_change.cycle_monthly')
                                                        }}
                                                        {{
                                                            t('billing.index.pending_change.on_date', {
                                                                date: formatDate(billingData.pending_plan_change.effective_date),
                                                            })
                                                        }}
                                                    </template>
                                                    <template v-else>
                                                        {{
                                                            t('billing.index.pending_change.scheduled_for', {
                                                                date: formatDate(billingData.pending_plan_change.effective_date),
                                                            })
                                                        }}
                                                    </template>
                                                </p>
                                                <p class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                                                    {{ t('billing.index.pending_change.access_note') }}
                                                </p>
                                                <div class="mt-3">
                                                    <ConfirmationDialog
                                                        :title="t('billing.index.pending_change.cancel_dialog.title')"
                                                        :description="t('billing.index.pending_change.cancel_dialog.description')"
                                                        :confirm-text="t('billing.index.pending_change.cancel_dialog.confirm')"
                                                        :cancel-text="t('billing.index.pending_change.cancel_dialog.cancel')"
                                                        @confirm="cancelScheduledPlanChange"
                                                    >
                                                        <template #trigger>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                :disabled="cancelScheduleForm.processing"
                                                                class="gap-2"
                                                            >
                                                                <Trash2 class="h-4 w-4" />
                                                                {{ t('billing.index.pending_change.cancel_schedule') }}
                                                            </Button>
                                                        </template>
                                                    </ConfirmationDialog>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                                {{
                                                    isYearly
                                                        ? billingData.current_package.yearly_price_formatted
                                                        : billingData.current_package.monthly_price_formatted
                                                }}

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
                                                    {{
                                                        billingData.subscription_canceled
                                                            ? t('billing.index.access_ends')
                                                            : t('billing.index.next_billing')
                                                    }}
                                                </span>
                                            </div>
                                            <span class="text-sm">
                                                {{ formatDate(billingData.subscription.current_period_end) }}
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
                                                        {{
                                                            t('billing.index.cancelled.description', {
                                                                date: formatDate(billingData.subscription.ends_at),
                                                            })
                                                        }}
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
                                            {{
                                                featuresExpanded
                                                    ? t('billing.index.show_less')
                                                    : t('billing.index.show_more', { count: hiddenFeaturesCount })
                                            }}
                                        </button>
                                    </div>
                                </CardContent>
                            </Card>

                            <AddonsSection :addons="billingData.addons" :subscribed="billingData.subscribed" @refresh="refreshAll" />
                        </div>

                        <!-- Usage & Limits -->
                        <div class="space-y-6">
                            <Card v-show="billingData.subscribed || billingData.on_trial">
                                <CardHeader>
                                    <CardTitle class="text-lg">{{ t('billing.index.management.title') }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="space-y-3">
                                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                                            <Link :href="route('billing.payment-methods')">
                                                <CreditCard class="h-4 w-4" />
                                                {{ t('billing.index.management.manage_payment_methods') }}
                                            </Link>
                                        </Button>
                                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                                            <Link :href="route('billing.invoices')">
                                                <Receipt class="h-4 w-4" />
                                                {{ t('billing.index.management.view_invoices') }}
                                            </Link>
                                        </Button>
                                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                                            <a target="_blank" :href="route('billing.portal')">
                                                <CreditCardIcon class="h-4 w-4" />
                                                {{ t('billing.index.management.go_to_stripe') }}
                                            </a>
                                        </Button>
                                        <Separator class="my-2" />
                                    </div>

                                    <template v-if="billingData.subscription && !billingData.subscription_canceled">
                                        <Button class="w-full justify-start gap-2" as-child>
                                            <Link :href="route('billing.packages')">
                                                <Package class="h-4 w-4" />
                                                {{ t('billing.index.management.change_plan') }}
                                            </Link>
                                        </Button>

                                        <ConfirmationDialog
                                            v-if="!billingData.subscription_canceled"
                                            :title="t('billing.index.cancel_dialog.title')"
                                            :description="t('billing.index.cancel_dialog.description')"
                                            :confirm-text="t('billing.index.cancel_dialog.confirm')"
                                            :cancel-text="t('billing.index.cancel_dialog.cancel')"
                                            @confirm="cancelSubscription(false)"
                                        >
                                            <template #trigger>
                                                <Button variant="destructive" :disabled="cancelForm.processing" class="w-full justify-start gap-2">
                                                    <Trash2 class="h-4 w-4" />
                                                    {{ t('billing.index.management.cancel_subscription') }}
                                                </Button>
                                            </template>
                                        </ConfirmationDialog>
                                    </template>
                                </CardContent>
                            </Card>

                            <!-- Usage Stats -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg">{{ t('billing.index.usage.title') }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div v-if="isLoadingUsage" class="flex items-center justify-center py-8">
                                        <Loader2 class="text-muted-foreground h-6 w-6 animate-spin" />
                                    </div>

                                    <div v-else-if="usageData" class="space-y-4">
                                        <!-- Messages Usage -->
                                        <div v-if="usageData.messages" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <MessageSquare class="h-4 w-4 text-blue-500" />
                                                    <span class="text-sm font-medium">{{ t('billing.index.usage.messages') }}</span>
                                                </div>
                                                <span class="text-muted-foreground text-sm">
                                                    {{ (usageData.messages.used ?? 0).toLocaleString() }} /
                                                    {{ (usageData.messages.limit ?? 0).toLocaleString() }}
                                                </span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-2 rounded-full transition-all duration-300"
                                                    :class="
                                                        getUsageColor(getUsagePercentage(usageData.messages.used ?? 0, usageData.messages.limit ?? 1))
                                                    "
                                                    :style="{
                                                        width: `${getUsagePercentage(usageData.messages.used ?? 0, usageData.messages.limit ?? 1)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{
                                                    t('billing.index.usage.remaining', {
                                                        count: (usageData.messages.remaining ?? 0).toLocaleString(),
                                                    })
                                                }}
                                            </div>
                                        </div>

                                        <!-- Pages Usage -->
                                        <div v-if="usageData.pages" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <FileText class="h-4 w-4 text-green-500" />
                                                    <span class="text-sm font-medium">{{ t('billing.index.usage.pages') }}</span>
                                                </div>
                                                <span class="text-muted-foreground text-sm">
                                                    {{ (usageData.pages.used ?? 0).toLocaleString() }} /
                                                    {{ (usageData.pages.limit ?? 0).toLocaleString() }}
                                                </span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-2 rounded-full transition-all duration-300"
                                                    :class="getUsageColor(getUsagePercentage(usageData.pages.used ?? 0, usageData.pages.limit ?? 1))"
                                                    :style="{
                                                        width: `${getUsagePercentage(usageData.pages.used ?? 0, usageData.pages.limit ?? 1)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ t('billing.index.usage.remaining', { count: (usageData.pages.remaining ?? 0).toLocaleString() }) }}
                                            </div>
                                        </div>

                                        <!-- Agents Usage -->
                                        <div v-if="usageData.agents" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <Bot class="h-4 w-4 text-purple-500" />
                                                    <span class="text-sm font-medium">{{ t('billing.index.usage.agents') }}</span>
                                                </div>
                                                <span class="text-muted-foreground text-sm">
                                                    {{ usageData.agents.used ?? 0 }} / {{ usageData.agents.limit ?? 0 }}
                                                </span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-2 rounded-full transition-all duration-300"
                                                    :class="
                                                        getUsageColor(getUsagePercentage(usageData.agents.used ?? 0, usageData.agents.limit ?? 1))
                                                    "
                                                    :style="{
                                                        width: `${getUsagePercentage(usageData.agents.used ?? 0, usageData.agents.limit ?? 1)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ t('billing.index.usage.remaining', { count: usageData.agents.remaining ?? 0 }) }}
                                            </div>
                                        </div>

                                        <!-- Links Usage -->
                                        <div v-if="usageData.links" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <Link2 class="h-4 w-4 text-orange-500" />
                                                    <span class="text-sm font-medium">{{ t('billing.index.usage.links') }}</span>
                                                </div>
                                                <span class="text-muted-foreground text-sm">
                                                    {{ (usageData.links.used ?? 0).toLocaleString() }} /
                                                    {{ (usageData.links.limit ?? 0).toLocaleString() }}
                                                </span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-2 rounded-full transition-all duration-300"
                                                    :class="getUsageColor(getUsagePercentage(usageData.links.used ?? 0, usageData.links.limit ?? 1))"
                                                    :style="{
                                                        width: `${getUsagePercentage(usageData.links.used ?? 0, usageData.links.limit ?? 1)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ t('billing.index.usage.remaining', { count: (usageData.links.remaining ?? 0).toLocaleString() }) }}
                                            </div>
                                        </div>

                                        <!-- Minutes Usage -->
                                        <div v-if="usageData.minutes" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <Timer class="h-4 w-4 text-cyan-500" />
                                                    <span class="text-sm font-medium">{{ t('billing.index.usage.minutes') }}</span>
                                                </div>
                                                <span class="text-muted-foreground text-sm">
                                                    {{ (usageData.minutes.used ?? 0).toLocaleString() }} /
                                                    {{ (usageData.minutes.limit ?? 0).toLocaleString() }}
                                                </span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-2 rounded-full transition-all duration-300"
                                                    :class="
                                                        getUsageColor(getUsagePercentage(usageData.minutes.used ?? 0, usageData.minutes.limit ?? 1))
                                                    "
                                                    :style="{
                                                        width: `${getUsagePercentage(usageData.minutes.used ?? 0, usageData.minutes.limit ?? 1)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{
                                                    t('billing.index.usage.remaining', { count: (usageData.minutes.remaining ?? 0).toLocaleString() })
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="py-4 text-center">
                                        <p class="text-muted-foreground text-sm">{{ t('billing.index.usage.load_error') }}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
