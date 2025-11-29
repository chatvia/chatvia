<script lang="ts" setup>
import { computed, ref, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import type { BreadcrumbItem } from '@/types';
import {
    CreditCard,
    CreditCardIcon,
    Package,
    Calendar,
    AlertTriangle,
    CheckCircle,
    Clock,
    Star,
    Zap,
    Trash2,
    Receipt,
    Loader2,
    MessageSquare,
    FileText,
    Bot,
    Link2,
    Timer
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: route('billing.index'),
    },
];

const cancelForm = useForm({
    immediately: false as boolean,
});

const resumeForm = useForm({});

const cancelScheduleForm = useForm({});

// Billing data
const billingData = ref<App.Data.Resources.Billing.BillingResourceData | null>(null);
const isLoadingBilling = ref(true);

// Usage data
const usageData = ref<App.Data.Resources.My.Usage.UsageResourceData | null>(null);
const isLoadingUsage = ref(true);

// ChatMessage credits dialog state
const isCreditsDialogOpen = ref(false);
const creditsQuantity = ref(1);
const basePrice = 10; // $10 USD per 1000 messages
const isPurchasing = ref(false);

const totalPrice = computed(() => {
    return (creditsQuantity.value * basePrice).toFixed(2);
});

const isValidQuantity = computed(() => {
    return creditsQuantity.value >= 1;
});

const fetchBillingData = async () => {
    try {
        isLoadingBilling.value = true;
        const response = await fetch(route('billing.data'), {
            headers: {
                'Accept': 'application/json',
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
                'Accept': 'application/json',
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

const purchaseCredits = () => {
    isPurchasing.value = true;
    // Small delay to show loading state before navigation
    setTimeout(() => {
        window.location.href = route('billing.addons.extra-message-credits', { quantity: creditsQuantity.value });
    }, 100);
};

const handleKeyPress = (event: KeyboardEvent) => {
    // Allow: backspace, delete, tab, escape, enter
    if ([8, 9, 27, 13, 46].indexOf(event.keyCode) !== -1 ||
        // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        (event.keyCode === 65 && event.ctrlKey === true) ||
        (event.keyCode === 67 && event.ctrlKey === true) ||
        (event.keyCode === 86 && event.ctrlKey === true) ||
        (event.keyCode === 88 && event.ctrlKey === true)) {
        return;
    }
    // Ensure that it is a number and stop the keypress if not
    if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) && (event.keyCode < 96 || event.keyCode > 105)) {
        event.preventDefault();
    }
};

const formatDate = (timestamp: number | string | null | undefined) => {
    if (!timestamp || timestamp === 0) return 'N/A';

    let ts: number;

    // Convert to number if it's a string
    if (typeof timestamp === 'string') {
        ts = parseInt(timestamp);
        if (isNaN(ts)) return 'N/A';
    } else {
        ts = timestamp;
    }

    // Handle both seconds and milliseconds timestamps
    const date = new Date(ts > 1000000000000 ? ts : ts * 1000);

    // Check if date is valid
    if (isNaN(date.getTime())) return 'N/A';

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
            return 'Active';
        case 'trialing':
            return 'Trial';
        case 'canceled':
            return 'Cancelled';
        case 'incomplete':
            return 'Incomplete';
        case 'past_due':
            return 'Past Due';
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
    return isYearly.value
        ? billingData.value.current_package.features.yearly
        : billingData.value.current_package.features.monthly;
});

const cancelSubscription = (immediately = false) => {
    cancelForm.immediately = immediately;
    cancelForm.post(route('billing.cancel'));
};

const resumeSubscription = () => {
    resumeForm.post(route('billing.resume'));
};

const cancelScheduledPlanChange = () => {
    cancelScheduleForm.post(route('billing.schedule.cancel'));
};

</script>

<template>
    <AppLayout title="Billing" :breadcrumbs="breadcrumbs">
        <Head title="Billing" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-0">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight flex items-center gap-3">
                            Billing
                        </h1>
                        <p class="text-muted-foreground mt-2 flex items-center gap-2 text-sm sm:text-base">
                            Manage your subscription, billing information, and usage limits
                        </p>
                    </div>
                </div>

                <!-- Stripe Payment Notice -->
                <div class="flex items-center justify-center py-3 px-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-lg">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <CreditCard class="h-4 w-4" />
                        <span>Payments securely processed by</span>
                        <span class="font-semibold text-[#635BFF]">Stripe</span>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoadingBilling" class="flex items-center justify-center py-16">
                    <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                </div>

                <template v-else-if="billingData">
                <!-- Trial Information Banner for Trial Users -->
                <div v-if="billingData.on_trial && !billingData.subscribed" class="mb-8">
                    <Card class="overflow-hidden border-emerald-200 dark:border-emerald-800 relative bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 dark:from-emerald-950/30 dark:via-teal-950/30 dark:to-cyan-950/30">
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-400/10 via-teal-400/10 to-cyan-400/10 dark:from-emerald-400/5 dark:via-teal-400/5 dark:to-cyan-400/5 opacity-70"></div>
                        <CardContent class="p-6 relative z-10">
                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 dark:from-emerald-400 dark:to-teal-500 flex items-center justify-center shadow-sm flex-shrink-0">
                                        <Zap class="h-5 w-5 text-white" />
                                    </div>
                                    <div>
                                        <h3 class="font-semibold bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-400 bg-clip-text text-transparent">🎉 You're on a Free Trial!</h3>
                                        <p class="text-sm text-emerald-600/70 dark:text-emerald-300/70 mt-1">
                                            Your {{ billingData.current_package.name }} trial ends on <span class="font-semibold">{{ billingData.trial_ends_at }}</span>
                                        </p>
                                        <p class="text-sm text-emerald-600/70 dark:text-emerald-300/70 mt-1">
                                            Your paid subscription will start automatically on {{ billingData.trial_ends_at }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Badge variant="outline" class="border-emerald-500 text-emerald-700 dark:text-emerald-300 w-fit">
                                        Trial Active
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Simple Subscription Encouragement for Non-Subscribed Users -->
                <div v-if="!billingData.subscribed && !billingData.on_trial" class="mb-8">
                    <Card class="overflow-hidden border-indigo-200 dark:border-indigo-800 relative bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:from-indigo-950/30 dark:via-purple-950/30 dark:to-pink-950/30">
                        <div class="absolute inset-0 bg-gradient-to-r from-indigo-400/10 via-purple-400/10 to-pink-400/10 dark:from-indigo-400/5 dark:via-purple-400/5 dark:to-pink-400/5 opacity-70"></div>
                        <div class="absolute -inset-[100%] animate-[spin_8s_linear_infinite] bg-gradient-to-r from-transparent via-indigo-400/20 to-transparent dark:via-indigo-400/10"></div>
                        <CardContent class="p-6 relative z-10">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 dark:from-indigo-400 dark:to-purple-500 flex items-center justify-center shadow-sm">
                                        <Zap class="h-5 w-5 text-white" />
                                    </div>
                                    <div>
                                        <h3 class="font-semibold bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 bg-clip-text text-transparent">Upgrade to Premium</h3>
                                        <p class="text-sm text-indigo-600/70 dark:text-indigo-300/70">Access advanced AI models, higher message limits & priority support</p>
                                    </div>
                                </div>
                                <Button size="sm" class="gap-2 whitespace-nowrap bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white border-0 shadow-sm" as-child>
                                    <Link :href="route('billing.packages')">
                                        <Star class="h-4 w-4" />
                                        View Plans
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-8 md:grid-cols-3">
                    <!-- Current Plan -->
                    <div class="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-3">
                                    <Package class="h-6 w-6 text-primary" />
                                    Current Plan
                                </CardTitle>
                                <CardDescription>
                                    Your active subscription plan and usage details
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <!-- Pending Plan Change Notice -->
                                <div v-if="billingData.pending_plan_change" class="p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                    <div class="flex items-start gap-3">
                                        <Calendar class="h-5 w-5 text-blue-500 mt-0.5" />
                                        <div>
                                            <h4 class="font-medium text-blue-800 dark:text-blue-200">
                                                Plan Change Scheduled
                                            </h4>
                                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                                <template v-if="billingData.pending_plan_change.from_package && billingData.pending_plan_change.to_package">
                                                    Your plan will change from <span class="font-medium">{{ billingData.pending_plan_change.from_package }}</span> to
                                                    <span class="font-medium">{{ billingData.pending_plan_change.to_package }}</span>
                                                    {{ billingData.pending_plan_change.is_yearly ? ' (Yearly)' : ' (Monthly)' }}
                                                    on {{ formatDate(billingData.pending_plan_change.effective_date) }}.
                                                </template>
                                                <template v-else>
                                                    You have a plan change scheduled for {{ formatDate(billingData.pending_plan_change.effective_date) }}.
                                                </template>
                                            </p>
                                                                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                                You'll continue to have access to your current plan features until then.
                            </p>
                            <div class="mt-3">
                                <ConfirmationDialog
                                    title="Cancel Scheduled Plan Change"
                                    description="Are you sure you want to cancel your scheduled plan change? You will remain on your current plan."
                                    confirm-text="Yes, Cancel Schedule"
                                    cancel-text="Keep Schedule"
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
                                            Cancel Schedule
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
                                        <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <Star class="h-6 w-6 text-primary" />
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-semibold">{{ billingData.current_package.name }}</h3>
                                            <p class="text-muted-foreground">{{ billingData.current_package.description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold">
                                            {{ isYearly ? billingData.current_package.yearly_price_formatted : billingData.current_package.monthly_price_formatted }}

                                            <span class="text-sm font-normal text-muted-foreground">
                                                /{{ isYearly ? 'Year' : 'Month' }}</span>
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
                                            <span class="font-medium">Status</span>
                                        </div>
                                        <Badge :variant="getStatusBadgeVariant(billingData.subscription?.stripe_status || 'trialing')">
                                            {{ getStatusLabel(billingData.subscription?.stripe_status || 'trialing') }}
                                        </Badge>
                                    </div>

                                    <!-- Trial Information -->
                                    <div v-if="billingData.on_trial && billingData.trial_ends_at" class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <Clock class="h-5 w-5 text-blue-500" />
                                            <span class="font-medium">Trial Ends</span>
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
                                                {{ billingData.subscription_canceled ? 'Access Ends' : 'Next Billing' }}
                                            </span>
                                        </div>
                                        <span class="text-sm">
                                            {{ formatDate(billingData.subscription.current_period_end) }}
                                        </span>
                                    </div>

                                    <!-- Cancellation Notice -->
                                    <div v-if="billingData.subscription_canceled && billingData.subscription" class="bg-orange-50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
                                        <div class="flex items-start gap-3">
                                            <AlertTriangle class="h-5 w-5 text-orange-500 mt-0.5" />
                                            <div>
                                                <p class="font-medium text-orange-800 dark:text-orange-200">
                                                    Subscription Cancelled
                                                </p>
                                                <p class="text-sm text-orange-700 dark:text-orange-300 mt-1">
                                                    Your subscription will end on {{ formatDate(billingData.subscription.ends_at) }}.
                                                    You can resume your subscription at any time before this date.
                                                </p>
                                                <Button
                                                    @click="resumeSubscription"
                                                    :disabled="resumeForm.processing"
                                                    size="sm"
                                                    class="mt-3"
                                                >
                                                    Resume Subscription
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Plan Features -->
                                <div class="space-y-4">
                                    <Separator />
                                    <h4 class="font-semibold">Plan Features</h4>
                                    <div class="grid gap-2">
                                        <div
                                            v-for="feature in currentFeatures"
                                            :key="feature"
                                            class="flex items-center gap-2"
                                        >
                                            <CheckCircle class="h-4 w-4 text-green-500" />
                                            <span class="text-sm">{{ feature }}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Usage & Limits -->
                    <div class="space-y-6">
                        <Card v-show="billingData.subscribed || billingData.on_trial">
                            <CardHeader>
                                <CardTitle class="text-lg">Management</CardTitle>

                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-3">
                                    <Button variant="outline" class="w-full gap-2 justify-start" as-child>
                                        <Link  :href="route('billing.payment-methods')">
                                            <CreditCard class="h-4 w-4" />
                                            Manage Payment Methods
                                        </Link>
                                    </Button>
                                    <Button variant="outline" class="w-full gap-2 justify-start" as-child>
                                        <Link :href="route('billing.invoices')">
                                            <Receipt class="h-4 w-4" />
                                            View Invoices
                                        </Link>
                                    </Button>
                                    <Button variant="outline" class="w-full gap-2 justify-start" as-child>
                                        <a target="_blank" :href="route('billing.portal')">
                                            <CreditCardIcon class="h-4 w-4" />
                                            Go to Stripe
                                        </a>
                                    </Button>
                                    <Separator class="my-2" />
                                </div>


                                <template v-if="billingData.subscription && !billingData.subscription_canceled">
                                    <Button class="w-full gap-2 justify-start" as-child>
                                        <Link :href="route('billing.packages')">
                                            <Package class="h-4 w-4" />
                                            Change Plan
                                        </Link>
                                    </Button>

                                    <ConfirmationDialog
                                        v-if="!billingData.subscription_canceled"
                                        title="Cancel Subscription"
                                        description="Are you sure you want to cancel your subscription? You will continue to have access until the end of your billing period."
                                        confirm-text="Yes, Cancel"
                                        cancel-text="Keep Subscription"
                                        @confirm="cancelSubscription(false)"
                                    >
                                        <template #trigger>
                                            <Button
                                                variant="destructive"
                                                :disabled="cancelForm.processing"
                                                class="w-full gap-2 justify-start"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                Cancel Subscription
                                            </Button>
                                        </template>
                                    </ConfirmationDialog>
                                </template>
                            </CardContent>
                        </Card>

                        <!-- Addons -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Addons</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <Dialog v-model:open="isCreditsDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button variant="outline" class="w-full gap-2 justify-start">
                                            <Zap class="h-4 w-4" />
                                            Buy Extra Messages
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent class="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle class="flex items-center gap-2">
                                                <Zap class="h-5 w-5 text-primary" />
                                                Purchase Message Credits
                                            </DialogTitle>
                                            <DialogDescription>
                                                Purchase additional message credits for your account. Each bundle includes 1000 messages that must be consumed within one month of purchase.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4 py-4">
                                            <div class="space-y-2">
                                                <Label for="quantity">Quantity (1000 messages per bundle)</Label>
                                                <Input
                                                    id="quantity"
                                                    v-model.number="creditsQuantity"
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    class="w-full"
                                                    @keydown="handleKeyPress"
                                                />
                                            </div>
                                            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-medium">Total Messages:</span>
                                                    <span class="font-bold">{{ (creditsQuantity * 1000).toLocaleString() }}</span>
                                                </div>
                                                <div class="flex justify-between items-center mt-2">
                                                    <span class="font-medium">Total Price:</span>
                                                    <span class="font-bold text-primary">${{ totalPrice }}</span>
                                                </div>
                                                <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                                                    <p class="text-xs text-muted-foreground">
                                                        ⚠️ Credits expire 30 days after purchase
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <DialogFooter class="gap-2">
                                            <Button variant="outline" @click="isCreditsDialogOpen = false; isPurchasing = false;">
                                                Cancel
                                            </Button>
                                            <Button @click="purchaseCredits" :disabled="isPurchasing || !isValidQuantity" class="gap-2">
                                                <Loader2 v-if="isPurchasing" class="h-4 w-4 animate-spin" />
                                                <CreditCard v-else class="h-4 w-4" />
                                                {{ isPurchasing ? 'Processing...' : `Purchase for $${totalPrice}` }}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </CardContent>
                        </Card>

                        <!-- Usage Stats -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Usage</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="isLoadingUsage" class="flex items-center justify-center py-8">
                                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                                </div>

                                <div v-else-if="usageData" class="space-y-4">
                                    <!-- Messages Usage -->
                                    <div v-if="usageData.messages" class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <MessageSquare class="h-4 w-4 text-blue-500" />
                                                <span class="text-sm font-medium">Messages</span>
                                            </div>
                                            <span class="text-sm text-muted-foreground">
                                                {{ (usageData.messages.used ?? 0).toLocaleString() }} / {{ (usageData.messages.limit ?? 0).toLocaleString() }}
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                            <div
                                                class="h-2 rounded-full transition-all duration-300"
                                                :class="getUsageColor(getUsagePercentage(usageData.messages.used ?? 0, usageData.messages.limit ?? 1))"
                                                :style="{ width: `${getUsagePercentage(usageData.messages.used ?? 0, usageData.messages.limit ?? 1)}%` }"
                                            ></div>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ (usageData.messages.remaining ?? 0).toLocaleString() }} remaining
                                        </div>
                                    </div>

                                    <!-- Pages Usage -->
                                    <div v-if="usageData.pages" class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <FileText class="h-4 w-4 text-green-500" />
                                                <span class="text-sm font-medium">Pages</span>
                                            </div>
                                            <span class="text-sm text-muted-foreground">
                                                {{ (usageData.pages.used ?? 0).toLocaleString() }} / {{ (usageData.pages.limit ?? 0).toLocaleString() }}
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                            <div
                                                class="h-2 rounded-full transition-all duration-300"
                                                :class="getUsageColor(getUsagePercentage(usageData.pages.used ?? 0, usageData.pages.limit ?? 1))"
                                                :style="{ width: `${getUsagePercentage(usageData.pages.used ?? 0, usageData.pages.limit ?? 1)}%` }"
                                            ></div>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ (usageData.pages.remaining ?? 0).toLocaleString() }} remaining
                                        </div>
                                    </div>

                                    <!-- Agents Usage -->
                                    <div v-if="usageData.agents" class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <Bot class="h-4 w-4 text-purple-500" />
                                                <span class="text-sm font-medium">Agents</span>
                                            </div>
                                            <span class="text-sm text-muted-foreground">
                                                {{ usageData.agents.used ?? 0 }} / {{ usageData.agents.limit ?? 0 }}
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                            <div
                                                class="h-2 rounded-full transition-all duration-300"
                                                :class="getUsageColor(getUsagePercentage(usageData.agents.used ?? 0, usageData.agents.limit ?? 1))"
                                                :style="{ width: `${getUsagePercentage(usageData.agents.used ?? 0, usageData.agents.limit ?? 1)}%` }"
                                            ></div>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ usageData.agents.remaining ?? 0 }} remaining
                                        </div>
                                    </div>

                                    <!-- Links Usage -->
                                    <div v-if="usageData.links" class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <Link2 class="h-4 w-4 text-orange-500" />
                                                <span class="text-sm font-medium">Links</span>
                                            </div>
                                            <span class="text-sm text-muted-foreground">
                                                {{ (usageData.links.used ?? 0).toLocaleString() }} / {{ (usageData.links.limit ?? 0).toLocaleString() }}
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                            <div
                                                class="h-2 rounded-full transition-all duration-300"
                                                :class="getUsageColor(getUsagePercentage(usageData.links.used ?? 0, usageData.links.limit ?? 1))"
                                                :style="{ width: `${getUsagePercentage(usageData.links.used ?? 0, usageData.links.limit ?? 1)}%` }"
                                            ></div>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ (usageData.links.remaining ?? 0).toLocaleString() }} remaining
                                        </div>
                                    </div>

                                    <!-- Minutes Usage -->
                                    <div v-if="usageData.minutes" class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <Timer class="h-4 w-4 text-cyan-500" />
                                                <span class="text-sm font-medium">Minutes</span>
                                            </div>
                                            <span class="text-sm text-muted-foreground">
                                                {{ (usageData.minutes.used ?? 0).toLocaleString() }} / {{ (usageData.minutes.limit ?? 0).toLocaleString() }}
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                            <div
                                                class="h-2 rounded-full transition-all duration-300"
                                                :class="getUsageColor(getUsagePercentage(usageData.minutes.used ?? 0, usageData.minutes.limit ?? 1))"
                                                :style="{ width: `${getUsagePercentage(usageData.minutes.used ?? 0, usageData.minutes.limit ?? 1)}%` }"
                                            ></div>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ (usageData.minutes.remaining ?? 0).toLocaleString() }} remaining
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="text-center py-4">
                                    <p class="text-sm text-muted-foreground">Unable to load usage data</p>
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
