<script setup lang="ts" generic="Bucket extends string">
/**
 * Body of the Tone and Sentiment pages, which differ only in their buckets,
 * labels and colours: gates on plan + per-agent switch, loads the breakdown
 * once the feature is on, and shows summary cards above the trend chart.
 * The page owns the layout (title, header icon) and passes `load`, which
 * calls its own endpoint and normalises the response.
 */
import type { AnalyticsFeature } from '@/api/analytics';
import { errorMessage } from '@/api/http';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, shallowRef } from 'vue';
import AnalyticsErrorState from './AnalyticsErrorState.vue';
import AnalyticsFeatureGate from './AnalyticsFeatureGate.vue';
import MessageDimensionChart from './MessageDimensionChart.vue';
import type { DimensionBreakdown, DimensionCopy, DimensionLineSeries, DimensionPieSlice, DimensionSummaryCard } from './messageDimension';
import { useAnalyticsFeature } from './useAnalyticsFeature';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    enabled: boolean;
    chatlogEnabled: boolean;
    /** Plan capability and per-agent settings flag; both use the same name. */
    feature: Extract<AnalyticsFeature, 'tone' | 'sentiment'>;
    load: () => Promise<DimensionBreakdown<Bucket>>;
    copy: DimensionCopy;
    lineSeries: DimensionLineSeries<Bucket>[];
    pieSlices: DimensionPieSlice<Bucket>[];
    summaryCards: DimensionSummaryCard<Bucket>[];
}>();

const loading = ref(true);
const data = shallowRef<DimensionBreakdown<Bucket> | null>(null);
const error = ref<string | null>(null);
const activeView = ref<'graph' | 'pie'>('graph');

const fetchData = async () => {
    loading.value = true;
    error.value = null;

    try {
        data.value = await props.load();
    } catch (err) {
        error.value = errorMessage(err, props.copy.loadError);
        console.error(`Error fetching ${props.feature} data:`, err);
    } finally {
        loading.value = false;
    }
};

const { hasAccess, isEnabled, enabling, isActive, enable } = useAnalyticsFeature({
    agentId: () => props.agent.external_id,
    feature: props.feature,
    enabled: props.enabled,
    chatlogEnabled: () => props.chatlogEnabled,
    onEnabled: fetchData,
});

// Count and share (one decimal) of each summary card's bucket.
const summaryStats = computed(() => {
    if (!data.value) return [];

    const totals = data.value.totals;
    const total = totals.total;

    return props.summaryCards.map((card) => ({
        ...card,
        count: totals[card.key],
        percentage: total > 0 ? ((totals[card.key] / total) * 100).toFixed(1) : '0',
    }));
});

onMounted(() => {
    if (isActive.value) {
        fetchData();
    }
});
</script>

<template>
    <AnalyticsFeatureGate
        :has-access="hasAccess"
        :enabled="isEnabled"
        :chatlog-enabled="chatlogEnabled"
        :enabling="enabling"
        :locked-description="copy.lockedDescription"
        :disabled-title="copy.disabledTitle"
        :disabled-description="copy.disabledDescription"
        :blocked-reason="copy.blockedReason"
        @enable="enable"
    >
        <!-- Live Analytics Indicator -->
        <div class="mb-6 flex justify-end">
            <div class="flex items-center gap-2">
                <div class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </div>
                <p class="text-muted-foreground text-sm">{{ copy.liveAnalytics }}</p>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
        </div>

        <AnalyticsErrorState v-else-if="error" :message="error" :retry-label="copy.tryAgain" @retry="fetchData" />

        <!-- Content -->
        <div v-else-if="data" class="space-y-6">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <Card v-for="stat in summaryStats" :key="stat.key">
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">{{ stat.label }}</p>
                                <Badge :variant="stat.badgeVariant" :class="stat.badgeClass"> {{ stat.percentage }}% </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ stat.count }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <MessageDimensionChart
                v-model:view="activeView"
                :data="data"
                :line-series="lineSeries"
                :pie-slices="pieSlices"
                :title="copy.chartTitle"
                :graph-label="copy.graph"
                :pie-label="copy.pieChart"
            />
        </div>
    </AnalyticsFeatureGate>
</template>
