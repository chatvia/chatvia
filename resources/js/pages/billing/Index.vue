<script lang="ts" setup>
import { computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import type { BreadcrumbItem } from '@/types';
import {
    CreditCard,
    Package,
    Calendar,
    AlertTriangle,
    CheckCircle,
    Clock,
    Star,
    Settings,
    Zap,
    Trash2,
    Receipt,
    CreditCardIcon
} from 'lucide-vue-next';

interface CurrentPackage {
    id: string;
    name: string;
    description: string;
    features: {
        monthly: string[];
        yearly: string[];
    };
    monthly_price: string;
    monthly_price_formatted: string;
    yearly_price_formatted: string;
    yearly_price: string;
    subscribed_to_yearly: boolean;
    limits: Record<string, any>;
};

const props = defineProps<{
    current_package: CurrentPackage;
    on_trial: boolean;
    trial_ends_at: string;
    subscription: any;
    subscribed: boolean;
    subscription_canceled: boolean;
}>();

const page = usePage();

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

const formatDate = (timestamp: number) => {
    return new Date(timestamp).toDateString();
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

const isSubscriptionActive = computed(() => {
    return props.subscription?.status === 'active' || props.on_trial;
});

const currentFeatures = computed(() => {
    return props.current_package.subscribed_to_yearly 
        ? props.current_package.features.yearly 
        : props.current_package.features.monthly;
});

const cancelSubscription = (immediately = false) => {
    cancelForm.immediately = immediately;
    cancelForm.post(route('billing.cancel'));
};

const resumeSubscription = () => {
    resumeForm.post(route('billing.resume'));
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
                            <CreditCard class="h-6 w-6 sm:h-8 sm:w-8 text-primary" />
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

                <!-- Simple Subscription Encouragement for Non-Subscribed Users -->
                <div v-if="!subscribed && !on_trial" class="mb-8">
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
                                <!-- Plan Info -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <Star class="h-6 w-6 text-primary" />
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-semibold">{{ current_package.name }}</h3>
                                            <p class="text-muted-foreground">{{ current_package.description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold">
                                            {{ current_package.subscribed_to_yearly ? current_package.yearly_price_formatted : current_package.monthly_price_formatted }}

                                            <span class="text-sm font-normal text-muted-foreground">
                                                /{{ current_package.subscribed_to_yearly ? 'Year':'Month' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Subscription Status -->
                                <div v-if="on_trial" class="space-y-4">
                                    <Separator />

                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <CheckCircle v-if="isSubscriptionActive" class="h-5 w-5 text-green-500" />
                                            <AlertTriangle v-else class="h-5 w-5 text-orange-500" />
                                            <span class="font-medium">Status</span>
                                        </div>
                                        <Badge :variant="getStatusBadgeVariant(subscription?.stripe_status || 'trialing')">
                                            {{ getStatusLabel(subscription?.stripe_status) }}
                                        </Badge>
                                    </div>

                                    <!-- Trial Information -->
                                    <div v-if="on_trial && trial_ends_at" class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <Clock class="h-5 w-5 text-blue-500" />
                                            <span class="font-medium">Trial Ends</span>
                                        </div>
                                        <span class="text-sm">
                                            {{ trial_ends_at }}
                                        </span>
                                    </div>

                                    <!-- Billing Period -->
                                    <div v-if="subscription && !on_trial" class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <Calendar class="h-5 w-5 text-blue-500" />
                                            <span class="font-medium">
                                                {{ subscription_canceled ? 'Access Ends' : 'Next Billing' }}
                                            </span>
                                        </div>
                                        <span class="text-sm">
                                            {{ formatDate(subscription.current_period_end) }}
                                        </span>
                                    </div>

                                    <!-- Cancellation Notice -->
                                    <div v-if="subscription_canceled && subscription" class="bg-orange-50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
                                        <div class="flex items-start gap-3">
                                            <AlertTriangle class="h-5 w-5 text-orange-500 mt-0.5" />
                                            <div>
                                                <p class="font-medium text-orange-800 dark:text-orange-200">
                                                    Subscription Cancelled
                                                </p>
                                                <p class="text-sm text-orange-700 dark:text-orange-300 mt-1">
                                                    Your subscription will end on {{ formatDate(subscription.ends_at) }}.
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
                        <Card v-show="subscribed">
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

                                <Button class="w-full gap-2 justify-start" as-child>
                                    <Link :href="route('billing.packages')">
                                        <Zap class="h-4 w-4" />
                                        Change Plan
                                    </Link>
                                </Button>
                                <template v-if="subscription">
                                    <ConfirmationDialog
                                        v-if="!subscription_canceled"
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

                        <!-- Quick Stats -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Usage</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm">AI Agents</span>
                                        <span class="text-sm font-medium">
                                            1
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm">Messages/Month</span>
                                        <span class="text-sm font-medium">
                                            1
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm">Storage</span>
                                        <span class="text-sm font-medium">
                                            1
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
