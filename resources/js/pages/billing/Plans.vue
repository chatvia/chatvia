<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bot, Building, Check, Crown, Gift, Package, Sparkles, Star, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { usePage } from '@inertiajs/vue3';

const page = usePage();

interface Package {
    id: string;
    name: string;
    description: string;
    features_monthly: Record<string, string>;
    features_yearly: Record<string, string>;
    capabilities: Record<string, string | number | boolean>;
    monthly_price: number;
    yearly_price: number;
    monthly_price_formatted: string;
    yearly_price_formatted: string;
    monthly_savings: {
        amount: number;
        percentage: number;
        formatted: string;
    };
    is_popular: boolean;
    contact_sales?: boolean;
}

interface LLM {
    name: string;
    icon: string;
    paid: boolean;
}

const props = defineProps<{
    packages: Package[];
    currentPackage: string;
    llms?: LLM[];
    billingConfig: {
        ui: {
            yearly_savings_percentage: number;
            yearly_savings_text: string;
            most_popular_text: string;
            current_plan_text: string;
            popular_badge_text: string;

            money_back_guarantee_days: number;
            yearly_upfront_text: string;
            yearly_benefit_text: string;
        };
        commonFeatures: Record<string, string>;
        packageIcons: Record<string, string>;
        currencySymbol: string;
        trial: {
            days: number;
            package: string;
        };
        discount: {
            percentage: number;
            ends_at: string | null;
        };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: route('billing.index'),
    },
    {
        title: 'Choose Plan',
        href: route('billing.packages'),
    },
];

const isYearly = ref(false);
const showLLMsModal = ref(false);
const currentLLMs = ref<LLM[]>([]);

// Discount functionality
const isDiscountActive = computed(() => {
    return (
        props.billingConfig.discount.percentage > 0 &&
        (!props.billingConfig.discount.ends_at || new Date(props.billingConfig.discount.ends_at) > new Date())
    );
});

const discountEndsAt = computed(() => {
    if (!props.billingConfig.discount.ends_at) return null;
    return new Date(props.billingConfig.discount.ends_at);
});

const formatDiscountEndDate = computed(() => {
    if (!discountEndsAt.value) return '';
    return discountEndsAt.value.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const _subscriptionForm = useForm({
    package: '',
    billing_cycle: 'monthly' as 'monthly' | 'yearly',
});

const _trialForm = useForm({
    package: 'starter',
});

const isSubscribing = ref(false);
const subscribingPackage = ref('');

const getPackagePrice = (pkg: Package) => {
    if (pkg.contact_sales) {
        return 'Custom';
    }

    let priceCents: number;

    if (isYearly.value && pkg.id !== 'free') {
        // Use yearly price divided by 12 to get monthly equivalent
        priceCents = pkg.yearly_price / 12;
    } else {
        // Use regular monthly price
        priceCents = pkg.monthly_price;
    }

    // Apply active discount if available
    if (isDiscountActive.value && pkg.id !== 'free') {
        priceCents = priceCents * (1 - props.billingConfig.discount.percentage / 100);
    }

    const roundedCents = Math.round(priceCents);
    const amount = (roundedCents / 100).toFixed(2);
    return `${props.billingConfig.currencySymbol}${amount}`;
};

const getOriginalPrice = (pkg: Package) => {
    if (pkg.id === 'free' || pkg.contact_sales || !isDiscountActive.value) return null;

    let originalPriceCents: number;

    if (isYearly.value) {
        // Use yearly price divided by 12 to get monthly equivalent (before discount)
        originalPriceCents = pkg.yearly_price / 12;
    } else {
        // Use regular monthly price
        originalPriceCents = pkg.monthly_price;
    }

    const amount = (originalPriceCents / 100).toFixed(2);
    return `${props.billingConfig.currencySymbol}${amount}`;
};

const getPackagePeriod = () => {
    return '/month';
};

const getYearlyTotal = (pkg: Package) => {
    if (!isYearly.value || pkg.id === 'free' || pkg.contact_sales) return null;

    let yearlyPriceCents = pkg.yearly_price;

    // Apply active discount if available
    if (isDiscountActive.value) {
        yearlyPriceCents = yearlyPriceCents * (1 - props.billingConfig.discount.percentage / 100);
    }

    const amount = (yearlyPriceCents / 100).toFixed(2);
    return `${props.billingConfig.currencySymbol}${amount}`;
};

const getSavingsText = (pkg: Package) => {
    if (!isYearly.value || pkg.monthly_savings.percentage === 0) return null;
    return `Save ${props.billingConfig.ui.yearly_savings_percentage}%`;
};

// Subscribe to a paid plan
const subscribeToPlan = (packageId: string) => {
    isSubscribing.value = true;
    subscribingPackage.value = packageId;
    window.location.href = route('subscribe', { package: packageId, yearly: isYearly.value });
};

const changeSubscription = (packageId: string) => {
    isSubscribing.value = true;
    subscribingPackage.value = packageId;

    const form = useForm({
        package: packageId,
        is_yearly: isYearly.value,
    });

    form.post(route('billing.subscription.change'), {
        onFinish: () => {
            isSubscribing.value = false;
            subscribingPackage.value = '';
        },
    });
};

// Determine if a package is an upgrade or downgrade
const getPackageAction = (packageId: string) => {
    if (isCurrentPackage(packageId)) {
        return 'current';
    }

    // Convert to array to ensure we have array methods
    const packagesArray = Array.isArray(props.packages) ? props.packages : (Object.values(props.packages) as Package[]);

    const currentPkg = packagesArray.find((pkg) => pkg.id === props.currentPackage);
    const targetPkg = packagesArray.find((pkg) => pkg.id === packageId);

    if (!currentPkg || !targetPkg) {
        return packageId === 'free' ? 'downgrade' : 'upgrade';
    }

    // Compare prices to determine upgrade vs downgrade
    const currentPrice = isYearly.value ? currentPkg.yearly_price : currentPkg.monthly_price;
    const targetPrice = isYearly.value ? targetPkg.yearly_price : targetPkg.monthly_price;

    if (targetPrice > currentPrice) {
        return 'upgrade';
    } else if (targetPrice < currentPrice) {
        return 'downgrade';
    } else {
        return 'same';
    }
};

// Resolve a package object from its id (props.packages may be array or map)
const findPackage = (packageId: string): Package | undefined => {
    const packagesArray = Array.isArray(props.packages) ? props.packages : (Object.values(props.packages) as Package[]);
    return packagesArray.find((pkg) => pkg.id === packageId);
};

// Get button text based on package action
const getButtonText = (packageId: string) => {
    if (findPackage(packageId)?.contact_sales) {
        return 'Contact sales';
    }

    const action = getPackageAction(packageId);

    switch (action) {
        case 'current':
            return props.billingConfig.ui.current_plan_text;
        case 'upgrade':
            return props.currentPackage === 'free' ? 'Upgrade' : 'Upgrade Plan';
        case 'downgrade':
            return packageId === 'free' ? 'Downgrade to Free' : 'Downgrade Plan';
        case 'same':
            return 'Switch Plan';
        default:
            return 'Select Plan';
    }
};

// Handle package selection
const handlePackageSelection = (packageId: string) => {
    // Contact-sales packages (Enterprise) aren't self-serve — send to the demo
    // form instead of the Stripe subscribe flow.
    if (findPackage(packageId)?.contact_sales) {
        window.location.href = route('demo');
        return;
    }

    const action = getPackageAction(packageId);

    if (action === 'current') {
        return;
    }

    if (page.props.subscribed) {
        changeSubscription(packageId);
    } else {
        subscribeToPlan(packageId);
    }
};

const isCurrentPackage = (packageId: string) => {
    return packageId === props.currentPackage;
};

const getPackageIcon = (packageId: string) => {
    const iconMap: Record<string, { icon: any; color: string }> = {
        gift: { icon: Gift, color: 'text-green-500' },
        zap: { icon: Zap, color: 'text-blue-500' },
        star: { icon: Star, color: 'text-yellow-500' },
        crown: { icon: Crown, color: 'text-purple-500' },
        building: { icon: Building, color: 'text-orange-500' },
    };

    const iconKey = props.billingConfig.packageIcons[packageId] || 'package';
    return iconMap[iconKey] || { icon: Package, color: 'text-gray-500' };
};

const getPackageIconBackground = (packageId: string) => {
    const backgroundMap: Record<string, string> = {
        gift: 'bg-green-100 dark:bg-green-900/20',
        zap: 'bg-blue-100 dark:bg-blue-900/20',
        star: 'bg-yellow-100 dark:bg-yellow-900/20',
        crown: 'bg-purple-100 dark:bg-purple-900/20',
        building: 'bg-orange-100 dark:bg-orange-900/20',
    };

    const iconKey = props.billingConfig.packageIcons[packageId] || 'package';
    return backgroundMap[iconKey] || 'bg-gray-100 dark:bg-gray-900/20';
};

const getFeatureHeader = (packageId: string, packages: Package[]) => {
    // Convert to array to ensure we have array methods
    const packagesArray = Array.isArray(packages) ? packages : (Object.values(packages) as Package[]);
    const packageIndex = packagesArray.findIndex((pkg: Package) => pkg.id === packageId);

    if (packageIndex === 0 || packageIndex === -1) {
        return 'Features included:';
    }

    const previousPackage = packagesArray[packageIndex - 1] as Package;
    return `Everything in ${previousPackage.name} +`;
};

const getPackageFeatures = (pkg: Package) => {
    return isYearly.value ? pkg.features_yearly : pkg.features_monthly;
};

const handleFeatureClick = (feature: string, index: number, packageKey: string) => {
    // Only first feature (index 0) in first two packages (free and starter)
    if (index === 0 && (packageKey === 'free' || packageKey === 'starter')) {
        const label = feature;
        if (label.includes('fast')) {
            // Free package: show only free models
            currentLLMs.value = (props.llms || []).filter((llm) => !llm.paid);
        } else {
            // Starter package: show ALL models (both paid and free)
            currentLLMs.value = props.llms || [];
        }
        showLLMsModal.value = true;
    }
};
</script>

<template>
    <AppLayout title="Choose Your Plan" :breadcrumbs="breadcrumbs">
        <Head title="Choose Your Plan" />

        <div class="container mx-auto max-w-7xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight">
                            <Sparkles class="text-primary h-8 w-8" />
                            Choose Your Plan
                        </h1>
                        <p class="text-muted-foreground">Select the perfect plan for your needs. You can change or cancel anytime.</p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link :href="route('billing.index')">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Billing
                        </Link>
                    </Button>
                </div>

                <!-- Billing Toggle -->
                <div class="flex items-center justify-center">
                    <div class="bg-muted flex items-center gap-3 rounded-lg p-1">
                        <Label
                            for="billing-toggle"
                            class="cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="{ 'bg-background shadow-sm': !isYearly }"
                            @click="
                                () => {
                                    isYearly = false;
                                }
                            "
                        >
                            Monthly
                        </Label>

                        <Label
                            for="billing-toggle"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="{ 'bg-background shadow-sm': isYearly }"
                            @click="
                                () => {
                                    isYearly = true;
                                }
                            "
                        >
                            Yearly
                            <Badge variant="secondary" class="text-xs">{{ billingConfig.ui.yearly_savings_text }}</Badge>
                        </Label>
                    </div>
                </div>

                <!-- Discount Banner -->
                <div v-if="isDiscountActive" class="rounded-lg bg-gradient-to-r from-green-500 to-emerald-600 p-4 text-center text-white">
                    <div class="mb-2 flex items-center justify-center gap-2">
                        <Sparkles class="h-5 w-5" />
                        <span class="text-lg font-bold">{{ billingConfig.discount.percentage }}% OFF Limited Time Offer!</span>
                        <Sparkles class="h-5 w-5" />
                    </div>
                    <p class="text-green-100">
                        <template v-if="billingConfig.discount.ends_at"> Offer ends {{ formatDiscountEndDate }} - Don't miss out! </template>
                        <template v-else> Limited time discount on all paid plans! </template>
                    </p>
                </div>

                <!-- Packages Grid -->
                <div class="flex flex-wrap justify-center gap-8">
                    <div v-for="(pkg, packageKey) in packages" :key="pkg.id" class="group relative w-full max-w-xs">
                        <!-- Popular Badge -->
                        <div v-if="pkg.is_popular" class="absolute -top-3 left-1/2 z-10 -translate-x-1/2">
                            <Badge class="bg-primary text-primary-foreground px-3 py-1 text-xs">
                                <Star class="mr-1 h-3 w-3" />
                                {{ billingConfig.ui.popular_badge_text }}
                            </Badge>
                        </div>

                        <!-- Package Card -->
                        <Card
                            class="relative flex h-full flex-col transition-all duration-200"
                            :class="[
                                subscribingPackage === pkg.id ? 'ring-primary scale-105 shadow-lg ring-2' : 'hover:scale-102 hover:shadow-lg',
                                pkg.is_popular ? 'border-primary/50' : '',
                                isCurrentPackage(pkg.id) ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/20' : '',
                            ]"
                        >
                            <!-- Current Plan Indicator -->
                            <div v-if="isCurrentPackage(pkg.id)" class="absolute top-4 right-4">
                                <Badge variant="secondary" class="text-xs">
                                    {{ billingConfig.ui.current_plan_text }}
                                </Badge>
                            </div>

                            <CardHeader class="pb-4 text-center">
                                <div
                                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg"
                                    :class="getPackageIconBackground(pkg.id)"
                                >
                                    <component :is="getPackageIcon(pkg.id).icon" class="h-6 w-6" :class="getPackageIcon(pkg.id).color" />
                                </div>
                                <CardTitle class="text-xl">{{ pkg.name }}</CardTitle>
                            </CardHeader>

                            <CardContent class="flex flex-1 flex-col">
                                <!-- Pricing -->
                                <div class="mb-6 text-center">
                                    <div class="space-y-1">
                                        <!-- Original price (strikethrough) when discount is active -->
                                        <div v-if="isDiscountActive && getOriginalPrice(pkg)" class="text-muted-foreground text-lg line-through">
                                            {{ getOriginalPrice(pkg) }}
                                        </div>
                                        <!-- Current price (discounted or regular) -->
                                        <div class="text-3xl font-bold" :class="{ 'text-green-600': isDiscountActive && pkg.id !== 'free' }">
                                            {{ getPackagePrice(pkg) }}
                                        </div>
                                        <!-- Discount badge -->
                                        <div
                                            v-if="isDiscountActive && pkg.id !== 'free' && !pkg.contact_sales"
                                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800"
                                        >
                                            <Sparkles class="h-3 w-3" />
                                            {{ billingConfig.discount.percentage }}% OFF
                                        </div>
                                    </div>
                                    <div v-if="!pkg.contact_sales" class="text-muted-foreground mt-2 text-sm">{{ getPackagePeriod() }}</div>
                                    <div v-if="getSavingsText(pkg)" class="mt-1 text-xs font-medium text-green-600">
                                        {{ getSavingsText(pkg) }}
                                    </div>
                                    <div v-if="getYearlyTotal(pkg)" class="text-muted-foreground mt-1 text-xs">
                                        Billed yearly at {{ getYearlyTotal(pkg) }}
                                    </div>
                                </div>

                                <!-- Action Button - Below price, above features -->
                                <div class="mb-6">
                                    <Button
                                        v-if="pkg.id === 'free'"
                                        :disabled="isCurrentPackage(pkg.id) || isSubscribing"
                                        class="h-10 w-full"
                                        variant="outline"
                                        @click.stop="handlePackageSelection(pkg.id)"
                                    >
                                        <template v-if="isSubscribing && subscribingPackage === pkg.id">
                                            <div
                                                class="border-muted-foreground/30 border-t-muted-foreground mr-2 h-4 w-4 animate-spin rounded-full border-2"
                                            />
                                            Processing...
                                        </template>
                                        <template v-else>
                                            {{ getButtonText(pkg.id) }}
                                        </template>
                                    </Button>

                                    <Button
                                        v-else
                                        :disabled="isCurrentPackage(pkg.id) || isSubscribing"
                                        class="h-10 w-full gap-2"
                                        :variant="pkg.is_popular ? 'default' : 'outline'"
                                        @click.stop="handlePackageSelection(pkg.id)"
                                    >
                                        <template v-if="isSubscribing && subscribingPackage === pkg.id">
                                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                            Processing...
                                        </template>
                                        <template v-else>
                                            {{ getButtonText(pkg.id) }}
                                        </template>
                                    </Button>
                                </div>

                                <!-- Features -->
                                <div class="flex-1 space-y-3">
                                    <h4 class="text-sm font-semibold">{{ getFeatureHeader(pkg.id, packages) }}</h4>
                                    <div class="space-y-2">
                                        <div
                                            v-for="([key, feature], index) in Object.entries(getPackageFeatures(pkg))"
                                            :key="key"
                                            class="flex items-start gap-2"
                                        >
                                            <Check class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                                            <a
                                                v-if="key === 'api'"
                                                href="https://chatvia.ai/docs/api"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-sm leading-relaxed text-blue-600 underline decoration-dotted underline-offset-2 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                {{ feature }}
                                            </a>
                                            <span
                                                v-else
                                                :class="[
                                                    'text-sm leading-relaxed',
                                                    index === 0 && (String(packageKey) === 'free' || String(packageKey) === 'starter')
                                                        ? 'cursor-pointer text-blue-600 underline decoration-dotted underline-offset-2 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300'
                                                        : '',
                                                ]"
                                                @click="handleFeatureClick(feature, index, String(packageKey))"
                                            >
                                                {{ feature }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Custom Enterprise Section -->
                <!-- Removing the old enterprise section since we moved it to a banner -->
            </div>
        </div>

        <!-- LLMs Modal -->
        <Dialog v-model:open="showLLMsModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Bot class="text-primary h-5 w-5" />
                        Available AI Models
                    </DialogTitle>
                    <DialogDescription> Choose from our comprehensive selection of state-of-the-art language models </DialogDescription>
                </DialogHeader>

                <div class="mt-4 grid max-h-96 gap-3 overflow-y-auto">
                    <div
                        v-for="llm in currentLLMs"
                        :key="llm.name"
                        class="hover:bg-muted/50 flex items-center gap-3 rounded-lg border p-3 transition-colors"
                    >
                        <div class="h-8 w-8 flex-shrink-0">
                            <img :src="`/images/llms/${llm.icon}`" :alt="llm.name" class="h-full w-full object-contain" loading="lazy" />
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-medium">{{ llm.name }}</div>
                        </div>
                    </div>

                    <div v-if="currentLLMs.length === 0" class="text-muted-foreground py-8 text-center">
                        <Bot class="mx-auto mb-2 h-12 w-12 opacity-50" />
                        <p>No models available</p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.scale-102 {
    transform: scale(1.02);
}
</style>
