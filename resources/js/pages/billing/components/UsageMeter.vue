<script lang="ts" setup>
// One "used / limit" row with a coloured progress bar on the billing page's
// usage card. The bar turns from green to red as the limit gets close.
import type { UsageStats } from '@/api/usage';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        icon: LucideIcon;
        iconClass: string;
        label: string;
        stat: UsageStats;
        /** Group thousands (1,000). Off for small counts shown as plain numbers. */
        localize?: boolean;
    }>(),
    { localize: true },
);

const { t } = useI18n();

const display = (value: number): string | number => (props.localize ? value.toLocaleString() : value);

const getUsagePercentage = (used: number, limit: number): number => {
    if (limit === 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
};

const getUsageColor = (percentage: number): string => {
    if (percentage >= 90) return 'bg-red-500';
    if (percentage >= 75) return 'bg-orange-500';
    if (percentage >= 50) return 'bg-yellow-500';
    return 'bg-green-500';
};

const percentage = computed(() => getUsagePercentage(props.stat.used ?? 0, props.stat.limit ?? 1));
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <component :is="icon" class="h-4 w-4" :class="iconClass" />
                <span class="text-sm font-medium">{{ label }}</span>
            </div>
            <span class="text-muted-foreground text-sm">
                {{ display(stat.used ?? 0) }} /
                {{ display(stat.limit ?? 0) }}
            </span>
        </div>
        <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
            <div
                class="h-2 rounded-full transition-all duration-300"
                :class="getUsageColor(percentage)"
                :style="{
                    width: `${percentage}%`,
                }"
            ></div>
        </div>
        <div class="text-muted-foreground text-xs">
            {{ t('billing.index.usage.remaining', { count: display(stat.remaining ?? 0) }) }}
        </div>
    </div>
</template>
