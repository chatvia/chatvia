<script lang="ts" setup>
// One plan on the plans page: icon, name, price (with any discount and the
// yearly total), the select button and the feature list. The page decides
// what selecting does; this card only reports the click.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useBrand } from '@/composables/useBrand';
import { Building, Check, Crown, Gift, Package, Sparkles, Star, Zap, type LucideIcon } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { usePlanPricing } from '../composables/usePlanPricing';
import { usePlanText } from '../composables/usePlanText';
import type { PlanBillingConfig, PlanPackage } from '../types';

const props = defineProps<{
    pkg: PlanPackage;
    billingConfig: PlanBillingConfig;
    isYearly: boolean;
    isCurrent: boolean;
    isSubscribing: boolean;
    subscribingPackage: string;
    buttonText: string;
    featureHeader: string;
}>();

const emit = defineEmits<{
    select: [packageId: string];
}>();

const { t } = useI18n();
const { brand } = useBrand();
const { planName, featureText } = usePlanText();
const { isDiscountActive, getPackagePrice, getOriginalPrice, getPackagePeriod, getYearlyTotal, getSavingsText } = usePlanPricing(
    () => props.billingConfig,
    () => props.isYearly,
);

const getPackageIcon = (packageId: string): { icon: LucideIcon; color: string } => {
    const iconMap: Record<string, { icon: LucideIcon; color: string }> = {
        gift: { icon: Gift, color: 'text-green-500' },
        zap: { icon: Zap, color: 'text-blue-500' },
        star: { icon: Star, color: 'text-yellow-500' },
        crown: { icon: Crown, color: 'text-purple-500' },
        building: { icon: Building, color: 'text-orange-500' },
    };

    const iconKey = props.billingConfig.packageIcons[packageId] || 'package';
    return iconMap[iconKey] || { icon: Package, color: 'text-gray-500' };
};

const getPackageIconBackground = (packageId: string): string => {
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

const getPackageFeatures = (pkg: PlanPackage): Domain.Billing.Data.Resources.Package.PlanFeatureData[] =>
    props.isYearly ? pkg.features.yearly : pkg.features.monthly;
</script>

<template>
    <div class="group relative w-full max-w-xs">
        <!-- Popular Badge -->
        <div v-if="pkg.is_popular" class="absolute -top-3 left-1/2 z-10 -translate-x-1/2">
            <Badge class="bg-primary text-primary-foreground px-3 py-1 text-xs">
                <Star class="me-1 h-3 w-3" />
                {{ t('billing.plans.most_popular') }}
            </Badge>
        </div>

        <!-- Package Card -->
        <Card
            class="relative flex h-full flex-col transition-all duration-200"
            :class="[
                subscribingPackage === pkg.id ? 'ring-primary scale-105 shadow-lg ring-2' : 'hover:scale-102 hover:shadow-lg',
                pkg.is_popular ? 'border-primary/50' : '',
                isCurrent ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/20' : '',
            ]"
        >
            <!-- Current Plan Indicator -->
            <div v-if="isCurrent" class="absolute end-4 top-4">
                <Badge variant="secondary" class="text-xs">
                    {{ t('billing.plans.current_plan') }}
                </Badge>
            </div>

            <CardHeader class="pb-4 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg" :class="getPackageIconBackground(pkg.id)">
                    <component :is="getPackageIcon(pkg.id).icon" class="h-6 w-6" :class="getPackageIcon(pkg.id).color" />
                </div>
                <CardTitle class="text-xl">{{ planName(pkg) }}</CardTitle>
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
                            {{ t('billing.plans.discount.off', { percent: billingConfig.discount.percentage }) }}
                        </div>
                    </div>
                    <div v-if="!pkg.contact_sales" class="text-muted-foreground mt-2 text-sm">{{ getPackagePeriod() }}</div>
                    <div v-if="getSavingsText(pkg)" class="mt-1 text-xs font-medium text-green-600">
                        {{ getSavingsText(pkg) }}
                    </div>
                    <div v-if="getYearlyTotal(pkg)" class="text-muted-foreground mt-1 text-xs">
                        {{ t('billing.plans.billed_yearly', { amount: getYearlyTotal(pkg) }) }}
                    </div>
                </div>

                <!-- Action Button - Below price, above features -->
                <div class="mb-6">
                    <Button
                        v-if="pkg.id === 'free'"
                        :disabled="isCurrent || isSubscribing"
                        class="h-10 w-full"
                        variant="outline"
                        @click.stop="emit('select', pkg.id)"
                    >
                        <template v-if="isSubscribing && subscribingPackage === pkg.id">
                            <div class="border-muted-foreground/30 border-t-muted-foreground me-2 h-4 w-4 animate-spin rounded-full border-2" />
                            {{ t('billing.common.processing') }}
                        </template>
                        <template v-else>
                            {{ buttonText }}
                        </template>
                    </Button>

                    <Button
                        v-else
                        :disabled="isCurrent || isSubscribing"
                        class="h-10 w-full gap-2"
                        :variant="pkg.is_popular ? 'default' : 'outline'"
                        @click.stop="emit('select', pkg.id)"
                    >
                        <template v-if="isSubscribing && subscribingPackage === pkg.id">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                            {{ t('billing.common.processing') }}
                        </template>
                        <template v-else>
                            {{ buttonText }}
                        </template>
                    </Button>
                </div>

                <!-- Features -->
                <div class="flex-1 space-y-3">
                    <h4 class="text-sm font-semibold">{{ featureHeader }}</h4>
                    <div class="space-y-2">
                        <div v-for="(feature, index) in getPackageFeatures(pkg)" :key="index" class="flex items-start gap-2">
                            <Check class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                            <a
                                v-if="feature.key === 'api' && brand.api_docs_url"
                                :href="brand.api_docs_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm leading-relaxed text-blue-600 underline decoration-dotted underline-offset-2 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                {{ featureText(feature) }}
                            </a>
                            <span v-else class="text-sm leading-relaxed">
                                {{ featureText(feature) }}
                            </span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.scale-102 {
    transform: scale(1.02);
}
</style>
