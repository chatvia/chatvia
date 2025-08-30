<script lang="ts" setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import Table from '@/components/ui/table/Table.vue';
import TableBody from '@/components/ui/table/TableBody.vue';
import TableCell from '@/components/ui/table/TableCell.vue';
import TableHead from '@/components/ui/table/TableHead.vue';
import TableHeader from '@/components/ui/table/TableHeader.vue';
import TableRow from '@/components/ui/table/TableRow.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import {
    Check,
    Star,
    Zap,
    Package,
    ArrowLeft,
    Crown,
    Sparkles,
    X,
    CreditCard,
    MessageCircle,
    Bot,
    Globe,
    EyeOff,
    Gift,
    Building,
} from 'lucide-vue-next';

import { usePage } from '@inertiajs/vue3';

const page = usePage();

interface Package {
    id: string;
    name: string;
    description: string;
    features_monthly: string[];
    features_yearly: string[];
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
}

interface Addon {
    id: string;
    name: string;
    description: string;
    price_cents: number;
    price_formatted: string;
    billing_unit: string;
    icon: string;
}

interface ComparisonFeature {
    key: string;
    name: string;
    description: string;
    capability_key: string;
}

interface LLM {
    name: string;
    icon: string;
    paid: boolean;
}

const props = defineProps<{
    packages: Package[];
    addons?: Addon[];
    currentPackage: string;
    stripeKey: string;
    comparisonFeatures: ComparisonFeature[];
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
        commonFeatures: string[];
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
    return props.billingConfig.discount.percentage > 0 && 
           (!props.billingConfig.discount.ends_at || new Date(props.billingConfig.discount.ends_at) > new Date());
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
        minute: '2-digit'
    });
});

const subscriptionForm = useForm({
    package: '',
    billing_cycle: 'monthly' as 'monthly' | 'yearly',
});

const trialForm = useForm({
    package: 'starter',
});

const isSubscribing = ref(false);
const subscribingPackage = ref('');

const getPackagePrice = (pkg: Package) => {
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
    if (pkg.id === 'free' || !isDiscountActive.value) return null;
    
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
    if (!isYearly.value || pkg.id === 'free') return null;
    
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
        is_yearly: isYearly.value
    });

    form.post(route('billing.subscription.change'), {
        onFinish: () => {
            isSubscribing.value = false;
            subscribingPackage.value = '';
        }
    });
};

// Determine if a package is an upgrade or downgrade
const getPackageAction = (packageId: string) => {
    if (isCurrentPackage(packageId)) {
        return 'current';
    }

    // Convert to array to ensure we have array methods
    const packagesArray = Array.isArray(props.packages) ? props.packages : Object.values(props.packages) as Package[];

    const currentPkg = packagesArray.find(pkg => pkg.id === props.currentPackage);
    const targetPkg = packagesArray.find(pkg => pkg.id === packageId);

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

// Get button text based on package action
const getButtonText = (packageId: string) => {
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
    const iconMap: Record<string, { icon: any, color: string }> = {
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

const getAddonIcon = (iconName: string) => {
    switch (iconName) {
        case 'credit-card':
            return CreditCard;
        case 'message-circle':
            return MessageCircle;
        case 'bot':
            return Bot;
        case 'globe':
            return Globe;
        case 'eye-off':
            return EyeOff;
        default:
            return Package;
    }
};

const getFeatureHeader = (packageId: string, packages: Package[]) => {
    // Convert to array to ensure we have array methods
    const packagesArray = Array.isArray(packages) ? packages : Object.values(packages) as Package[];
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

const formatFeatureValue = (value: string | number | boolean) => {
    if (typeof value === 'boolean') {
        return value;
    }
    if (typeof value === 'number') {
        if (value >= 1000) {
            return (value / 1000).toFixed(0) + 'K';
        }
        return value.toString();
    }
    return value; // string
};

const handleFeatureClick = (feature: string, index: number, packageKey: string) => {
    // Only first feature (index 0) in first two packages (free and starter)
    if (index === 0 && (packageKey === 'free' || packageKey === 'starter')) {
        if (feature.includes('fast')) {
            // Free package: show only free models
            currentLLMs.value = (props.llms || []).filter(llm => !llm.paid);
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
                        <h1 class="text-3xl font-bold tracking-tight flex items-center gap-3">
                            <Sparkles class="h-8 w-8 text-primary" />
                            Choose Your Plan
                        </h1>
                        <p class="text-muted-foreground">
                            Select the perfect plan for your needs. You can change or cancel anytime.
                        </p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link :href="route('billing.index')">
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Back to Billing
                        </Link>
                    </Button>
                </div>

                <!-- Billing Toggle -->
                <div class="flex items-center justify-center">
                    <div class="flex items-center gap-3 p-1 bg-muted rounded-lg">
                        <Label
                            for="billing-toggle"
                            class="text-sm font-medium cursor-pointer px-3 py-2 rounded-md transition-colors"
                            :class="{ 'bg-background shadow-sm': !isYearly }"
                            @click="() => { isYearly = false }"
                        >
                            Monthly
                        </Label>

                        <Label
                            for="billing-toggle"
                            class="text-sm font-medium cursor-pointer px-3 py-2 rounded-md transition-colors flex items-center gap-2"
                            :class="{ 'bg-background shadow-sm': isYearly }"
                            @click="() => { isYearly = true }"
                        >
                            Yearly
                            <Badge variant="secondary" class="text-xs">{{ billingConfig.ui.yearly_savings_text }}</Badge>
                        </Label>
                    </div>
                </div>

                <!-- Discount Banner -->
                <div v-if="isDiscountActive" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg p-4 text-center">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <Sparkles class="h-5 w-5" />
                        <span class="font-bold text-lg">{{ billingConfig.discount.percentage }}% OFF Limited Time Offer!</span>
                        <Sparkles class="h-5 w-5" />
                    </div>
                    <p class="text-green-100">
                        <template v-if="billingConfig.discount.ends_at">
                            Offer ends {{ formatDiscountEndDate }} - Don't miss out!
                        </template>
                        <template v-else>
                            Limited time discount on all paid plans!
                        </template>
                    </p>
                </div>

                <!-- Packages Grid -->
                <div class="flex justify-center">
                    <div class="grid gap-8 grid-cols-1 md:grid-cols-2 lg:grid-cols-4 max-w-6xl">
                        <div
                            v-for="(pkg, packageKey) in packages"
                            :key="pkg.id"
                            class="group relative w-full"
                        >
                            <!-- Popular Badge -->
                            <div
                                v-if="pkg.is_popular"
                                class="absolute -top-3 left-1/2 -translate-x-1/2 z-10"
                            >
                                <Badge class="bg-primary text-primary-foreground px-3 py-1 text-xs">
                                    <Star class="h-3 w-3 mr-1" />
                                    {{ billingConfig.ui.popular_badge_text }}
                                </Badge>
                            </div>

                            <!-- Package Card -->
                            <Card
                                class="h-full transition-all duration-200 relative flex flex-col"
                                :class="[
                                    subscribingPackage === pkg.id
                                        ? 'ring-2 ring-primary shadow-lg scale-105'
                                        : 'hover:shadow-lg hover:scale-102',
                                    pkg.is_popular ? 'border-primary/50' : '',
                                    isCurrentPackage(pkg.id) ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800' : ''
                                ]"
                            >
                                <!-- Current Plan Indicator -->
                                <div
                                    v-if="isCurrentPackage(pkg.id)"
                                    class="absolute top-4 right-4"
                                >
                                    <Badge variant="secondary" class="text-xs">
                                        {{ billingConfig.ui.current_plan_text }}
                                    </Badge>
                                </div>

                                <CardHeader class="text-center pb-4">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center mx-auto mb-4" :class="getPackageIconBackground(pkg.id)">
                                        <component :is="getPackageIcon(pkg.id).icon" class="h-6 w-6" :class="getPackageIcon(pkg.id).color" />
                                    </div>
                                    <CardTitle class="text-xl">{{ pkg.name }}</CardTitle>

                                </CardHeader>

                                <CardContent class="flex-1 flex flex-col">
                                    <!-- Pricing -->
                                    <div class="text-center mb-6">
                                        <div class="space-y-1">
                                            <!-- Original price (strikethrough) when discount is active -->
                                            <div v-if="isDiscountActive && getOriginalPrice(pkg)" class="text-lg text-muted-foreground line-through">
                                                {{ getOriginalPrice(pkg) }}
                                            </div>
                                            <!-- Current price (discounted or regular) -->
                                            <div class="text-3xl font-bold" :class="{ 'text-green-600': isDiscountActive && pkg.id !== 'free' }">
                                                {{ getPackagePrice(pkg) }}
                                            </div>
                                            <!-- Discount badge -->
                                            <div v-if="isDiscountActive && pkg.id !== 'free'" class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                                <Sparkles class="h-3 w-3" />
                                                {{ billingConfig.discount.percentage }}% OFF
                                            </div>
                                        </div>
                                        <div class="text-sm text-muted-foreground mt-2">{{ getPackagePeriod() }}</div>
                                        <div v-if="getSavingsText(pkg)" class="text-xs text-green-600 font-medium mt-1">
                                            {{ getSavingsText(pkg) }}
                                        </div>
                                        <div v-if="getYearlyTotal(pkg)" class="text-xs text-muted-foreground mt-1">
                                            Billed yearly at {{ getYearlyTotal(pkg) }}
                                        </div>
                                    </div>

                                    <!-- Action Button - Below price, above features -->
                                    <div class="mb-6">
                                        <Button
                                            v-if="pkg.id === 'free'"
                                            :disabled="isCurrentPackage(pkg.id) || isSubscribing"
                                            class="w-full h-10"
                                            variant="outline"
                                            @click.stop="handlePackageSelection(pkg.id)"
                                        >
                                            <template v-if="isSubscribing && subscribingPackage === pkg.id">
                                                <div class="h-4 w-4 rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground animate-spin mr-2" />
                                                Processing...
                                            </template>
                                            <template v-else>
                                                {{ getButtonText(pkg.id) }}
                                            </template>
                                        </Button>

                                        <Button
                                            v-else
                                            :disabled="isCurrentPackage(pkg.id) || isSubscribing"
                                            class="w-full h-10 gap-2"
                                            :variant="pkg.is_popular ? 'default' : 'outline'"
                                            @click.stop="handlePackageSelection(pkg.id)"
                                        >
                                            <template v-if="isSubscribing && subscribingPackage === pkg.id">
                                                <div class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                                                Processing...
                                            </template>
                                            <template v-else>
                                                {{ getButtonText(pkg.id) }}
                                            </template>
                                        </Button>
                                    </div>

                                    <!-- Features -->
                                    <div class="space-y-3 flex-1">
                                        <h4 class="font-semibold text-sm">{{ getFeatureHeader(pkg.id, packages) }}</h4>
                                        <div class="space-y-2">
                                            <div
                                                v-for="(feature, index) in getPackageFeatures(pkg)"
                                                :key="feature"
                                                class="flex items-start gap-2"
                                            >
                                                <Check class="h-4 w-4 text-green-500 flex-shrink-0 mt-0.5" />
                                                <span
                                                    :class="[
                                                        'text-sm leading-relaxed',
                                                        (index === 0 && (String(packageKey) === 'free' || String(packageKey) === 'starter'))
                                                            ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 cursor-pointer underline decoration-dotted underline-offset-2'
                                                            : ''
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
                </div>

                <!-- Add-ons Section -->
                <div v-if="addons && addons.length > 0" class="space-y-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold tracking-tight mb-2">Add-ons</h2>
                        <p class="text-muted-foreground">
                            Enhance your plan with additional features and capabilities
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-1 max-w-4xl mx-auto">
                        <div
                            v-for="addon in addons"
                            :key="addon.id"
                            class="flex items-center justify-between p-6 bg-background border rounded-lg hover:shadow-md transition-shadow"
                        >
                            <div class="flex items-start gap-4 flex-1">
                                <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center flex-shrink-0">
                                    <component
                                        :is="getAddonIcon(addon.icon)"
                                        class="h-6 w-6 text-muted-foreground"
                                    />
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-lg mb-1">{{ addon.name }}</h3>
                                    <p class="text-sm text-muted-foreground mb-2">{{ addon.description }}</p>
                                    <p class="text-xs text-muted-foreground">{{ addon.billing_unit }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 flex-shrink-0">
                                <div class="text-right">
                                    <div class="text-2xl font-bold">{{ addon.price_formatted }}</div>
                                    <div class="text-xs text-muted-foreground">Total {{ addon.billing_unit }}</div>
                                </div>
                                <Button variant="outline" class="whitespace-nowrap">
                                    Get Add-on
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Enterprise Section -->
                <!-- Removing the old enterprise section since we moved it to a banner -->

                <!-- Comparison Table -->
                <div class="bg-muted/50 rounded-lg p-6 space-y-6">
                    <h3 class="text-xl font-semibold flex items-center gap-2">
                        <Package class="h-5 w-5 text-primary" />
                        Feature Comparison
                    </h3>

                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[200px]">Feature</TableHead>
                                    <TableHead v-for="pkg in packages" :key="pkg.id" class="text-center">
                                        {{ pkg.name }}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="feature in comparisonFeatures" :key="feature.key">
                                    <TableCell class="font-medium">
                                        <div>
                                            <div class="font-semibold">{{ feature.name }}</div>
                                            <div class="text-xs text-muted-foreground">{{ feature.description }}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell v-for="pkg in packages" :key="pkg.id" class="text-center">
                                        <div class="flex justify-center items-center">
                                            <template v-if="typeof pkg.capabilities?.[feature.capability_key] === 'boolean'">
                                                <Check v-if="pkg.capabilities[feature.capability_key]" class="h-4 w-4 text-green-500" />
                                                <X v-else class="h-4 w-4 text-red-500" />
                                            </template>
                                            <template v-else>
                                                <span class="text-sm font-medium">
                                                    {{ formatFeatureValue(pkg.capabilities?.[feature.capability_key]) }}
                                                </span>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="bg-muted/50 rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold flex items-center gap-2">
                        <Package class="h-5 w-5 text-primary" />
                        What's included with all plans
                    </h3>
                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="feature in billingConfig.commonFeatures"
                            :key="feature"
                            class="flex items-center gap-2"
                        >
                            <Check class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ feature }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LLMs Modal -->
        <Dialog v-model:open="showLLMsModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Bot class="h-5 w-5 text-primary" />
                        Available AI Models
                    </DialogTitle>
                    <DialogDescription>
                        Choose from our comprehensive selection of state-of-the-art language models
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 mt-4 max-h-96 overflow-y-auto">
                    <div
                        v-for="llm in currentLLMs"
                        :key="llm.name"
                        class="flex items-center gap-3 p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                    >
                        <div class="w-8 h-8 flex-shrink-0">
                            <img
                                :src="`/images/llms/${llm.icon}`"
                                :alt="llm.name"
                                class="w-full h-full object-contain"
                                loading="lazy"
                            />
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm">{{ llm.name }}</div>
                        </div>
                    </div>

                    <div v-if="currentLLMs.length === 0" class="text-center py-8 text-muted-foreground">
                        <Bot class="h-12 w-12 mx-auto mb-2 opacity-50" />
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
