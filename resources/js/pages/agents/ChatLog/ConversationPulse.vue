<script lang="ts" setup>
import LockedFeature from '@/components/LockedFeature.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import {
    CategoryScale,
    Chart as ChartJS,
    Tooltip as ChartTooltip,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    type ChartOptions,
} from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { Activity, HelpCircle, LoaderCircle, MessageSquare, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, watch } from 'vue';
import { Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';
import { useChatLog } from './useChatLog';

const { t } = useI18n();

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, ChartTooltip, Legend, Filler, ChartDataLabels);

const {
    currentConversationId,
    activeTab,
    pulseData,
    isLoadingPulse,
    pulseError,
    fetchPulseData,
    getToneDisplayName: _getToneDisplayName,
    getToneEmoji: _getToneEmoji,
    getToneBadgeColor: _getToneBadgeColor,
    getSentimentBadgeColor: _getSentimentBadgeColor,
    getPulsePointColor,
} = useChatLog();

const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('pulse'));

// Load pulse data when tab becomes active
watch(
    () => activeTab.value,
    (newTab) => {
        if (newTab === 'pulse' && currentConversationId.value && !pulseData.value && hasAccess.value) {
            fetchPulseData();
        }
    },
);

// Reset and reload when conversation changes
watch(
    () => currentConversationId.value,
    () => {
        if (activeTab.value === 'pulse' && currentConversationId.value) {
            fetchPulseData();
        }
    },
);

// Load on mount if already on pulse tab
onMounted(() => {
    if (activeTab.value === 'pulse' && currentConversationId.value && !pulseData.value) {
        fetchPulseData();
    }
});

function _capitalize(str: string): string {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Chart data
const pulseChartData = computed(() => {
    if (!pulseData.value?.messages?.length) return null;
    const messages = pulseData.value.messages;

    return {
        labels: messages.map((_: any, i: number) => i + 1),
        datasets: [
            {
                label: t('agents.chatlog.pulse.mood'),
                data: messages.map((m: any) => m.sentiment_score),
                borderColor: 'rgb(99, 102, 241)',
                backgroundColor: 'transparent',
                fill: false,
                tension: 0,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: messages.map((m: any) => getPulsePointColor(m.sentiment_score)),
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                borderWidth: 2,
            },
        ],
    };
});

// Chart options
const pulseChartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { top: 10, right: 20 } },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            padding: 12,
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
                title: (items) => {
                    const index = items[0]?.dataIndex;
                    if (index !== undefined && pulseData.value?.messages[index]) {
                        return t('agents.chatlog.pulse.message_number', { number: index + 1 });
                    }
                    return '';
                },
                label: (item) => {
                    const index = item.dataIndex;
                    if (index !== undefined && pulseData.value?.messages[index]) {
                        const msg = pulseData.value.messages[index];
                        const lines = [];
                        if (msg.tone) lines.push(t('agents.chatlog.pulse.tone_label', { value: msg.tone }));
                        if (msg.sentiment) lines.push(t('agents.chatlog.pulse.sentiment_label', { value: msg.sentiment }));
                        if (msg.content_preview) lines.push(`"${msg.content_preview}"`);
                        return lines;
                    }
                    return '';
                },
            },
        },
        datalabels: { display: false },
    },
    scales: {
        x: { display: false },
        y: {
            min: -1.5,
            max: 1.5,
            grid: { color: 'rgba(156, 163, 175, 0.1)' },
            border: { display: false },
            ticks: {
                color: 'rgb(156, 163, 175)',
                font: { size: 11 },
                callback: (value) => {
                    if (value === 1) return t('agents.chatlog.pulse.scale_good');
                    if (value === 0) return t('agents.chatlog.pulse.scale_neutral');
                    if (value === -1) return t('agents.chatlog.pulse.scale_bad');
                    return '';
                },
                stepSize: 1,
            },
        },
    },
    interaction: { intersect: false, mode: 'index' },
}));
</script>

<template>
    <!-- Locked Feature -->
    <LockedFeature v-if="!hasAccess" :description="t('agents.chatlog.pulse.locked_description')" />

    <!-- No Conversation Selected -->
    <div v-else-if="!currentConversationId" class="text-muted-foreground flex flex-col items-center justify-center py-20">
        <Activity class="mb-3 h-10 w-10 opacity-30" />
        <p class="text-sm">{{ t('agents.chatlog.pulse.select_conversation') }}</p>
    </div>

    <!-- Loading State -->
    <div v-else-if="isLoadingPulse" class="flex items-center justify-center py-20">
        <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
    </div>

    <!-- Error State -->
    <div v-else-if="pulseError" class="text-muted-foreground flex flex-col items-center justify-center py-20">
        <p class="text-sm">{{ pulseError }}</p>
    </div>

    <!-- No Data State -->
    <div v-else-if="!pulseData?.messages?.length" class="text-muted-foreground flex flex-col items-center justify-center py-20">
        <MessageSquare class="mb-3 h-10 w-10 opacity-30" />
        <p class="text-sm">{{ t('agents.chatlog.pulse.no_messages_title') }}</p>
        <p class="mt-1 text-xs">{{ t('agents.chatlog.pulse.no_messages_hint') }}</p>
    </div>

    <!-- Pulse Content -->
    <div v-else class="space-y-5 p-5">
        <!-- Header with Refresh -->
        <div class="flex items-center justify-between">
            <h3 class="text-muted-foreground text-sm font-medium">{{ t('agents.chatlog.pulse.title') }}</h3>
            <Button variant="ghost" size="sm" @click="fetchPulseData" :disabled="isLoadingPulse" class="h-8 gap-1.5">
                <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isLoadingPulse }" />
                {{ t('agents.chatlog.pulse.refresh') }}
            </Button>
        </div>

        <!-- Summary Table -->
        <div class="bg-card rounded-lg border">
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    <tr>
                        <td class="text-muted-foreground w-1/3 px-4 py-3 font-medium">
                            <div class="flex items-center gap-1.5">
                                {{ t('agents.chatlog.pulse.trend') }}
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <HelpCircle class="text-muted-foreground/50 h-3.5 w-3.5 cursor-help" />
                                        </TooltipTrigger>
                                        <TooltipContent side="right" class="max-w-[240px]">
                                            <p class="text-xs">
                                                <i18n-t keypath="agents.chatlog.pulse.trend_tooltip" tag="span" scope="global">
                                                    <template #first><strong>{{ t('agents.chatlog.pulse.trend_first') }}</strong></template>
                                                    <template #last><strong>{{ t('agents.chatlog.pulse.trend_last') }}</strong></template>
                                                </i18n-t>
                                            </p>
                                            <ul class="mt-1.5 space-y-0.5 text-xs">
                                                <li><span class="text-green-500">{{ t('agents.chatlog.pulse.trend_improving') }}</span> {{ t('agents.chatlog.pulse.trend_improving_hint') }}</li>
                                                <li><span class="text-red-500">{{ t('agents.chatlog.pulse.trend_declining') }}</span> {{ t('agents.chatlog.pulse.trend_declining_hint') }}</li>
                                                <li>{{ t('agents.chatlog.pulse.trend_stable') }}</li>
                                            </ul>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="capitalize"
                                :class="{
                                    'text-green-600 dark:text-green-400': pulseData.summary.trend === 'improving',
                                    'text-red-600 dark:text-red-400': pulseData.summary.trend === 'declining',
                                }"
                            >
                                <template v-if="pulseData.summary.trend === 'improving'">↗ </template>
                                <template v-else-if="pulseData.summary.trend === 'declining'">↘ </template>
                                {{ pulseData.summary.trend }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted-foreground px-4 py-3 font-medium">
                            <div class="flex items-center gap-1.5">
                                {{ t('agents.chatlog.pulse.resolution') }}
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <HelpCircle class="text-muted-foreground/50 h-3.5 w-3.5 cursor-help" />
                                        </TooltipTrigger>
                                        <TooltipContent side="right" class="max-w-[240px]">
                                            <p class="text-xs">{{ t('agents.chatlog.pulse.resolution_tooltip') }}</p>
                                            <ul class="mt-1.5 space-y-0.5 text-xs">
                                                <li><span class="text-green-500">{{ t('agents.chatlog.pulse.resolution_resolved') }}</span> {{ t('agents.chatlog.pulse.resolution_resolved_hint') }}</li>
                                                <li>{{ t('agents.chatlog.pulse.resolution_ongoing_hint') }}</li>
                                            </ul>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="pulseData.summary.resolution" class="text-green-600 dark:text-green-400">{{ t('agents.chatlog.pulse.resolved') }}</span>
                            <span v-else class="text-muted-foreground">{{ t('agents.chatlog.pulse.ongoing') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted-foreground px-4 py-3 font-medium">{{ t('agents.chatlog.pulse.user_messages') }}</td>
                        <td class="px-4 py-3">{{ pulseData.conversation.user_messages }}</td>
                    </tr>
                    <tr v-if="pulseData.summary.started_as?.tone || pulseData.summary.started_as?.sentiment">
                        <td class="text-muted-foreground px-4 py-3 font-medium">{{ t('agents.chatlog.pulse.started_as') }}</td>
                        <td class="px-4 py-3 capitalize">
                            {{ pulseData.summary.started_as?.tone || pulseData.summary.started_as?.sentiment || '—' }}
                        </td>
                    </tr>
                    <tr v-if="pulseData.summary.ended_as?.tone || pulseData.summary.ended_as?.sentiment">
                        <td class="text-muted-foreground px-4 py-3 font-medium">{{ t('agents.chatlog.pulse.ended_as') }}</td>
                        <td class="px-4 py-3 capitalize">
                            {{ pulseData.summary.ended_as?.tone || pulseData.summary.ended_as?.sentiment || '—' }}
                        </td>
                    </tr>
                    <tr v-if="pulseData.summary.peak_negative">
                        <td class="text-muted-foreground px-4 py-3 font-medium">{{ t('agents.chatlog.pulse.peak_negative') }}</td>
                        <td class="px-4 py-3 capitalize">
                            {{ pulseData.summary.peak_negative.tone || pulseData.summary.peak_negative.sentiment }}
                            <span class="text-muted-foreground"> · {{ t('agents.chatlog.pulse.message_number', { number: pulseData.summary.peak_negative.index }) }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Chart -->
        <div class="bg-card rounded-lg border p-4">
            <div class="mb-4 text-sm font-medium">{{ t('agents.chatlog.pulse.sentiment_timeline') }}</div>
            <div class="h-[240px]">
                <Line v-if="pulseChartData" :data="pulseChartData" :options="pulseChartOptions" />
            </div>
            <!-- Legend -->
            <div class="text-muted-foreground mt-4 flex items-center justify-center gap-6 border-t pt-4 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-green-500"></span>
                    <span>{{ t('agents.chatlog.pulse.scale_good') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-yellow-500"></span>
                    <span>{{ t('agents.chatlog.pulse.scale_neutral') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-red-500"></span>
                    <span>{{ t('agents.chatlog.pulse.scale_bad') }}</span>
                </div>
            </div>
        </div>

        <!-- Message Timeline -->
        <div class="bg-card rounded-lg border">
            <div class="border-b px-4 py-3">
                <span class="text-sm font-medium">{{ t('agents.chatlog.pulse.message_details') }}</span>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    <tr v-for="msg in pulseData.messages" :key="msg.index">
                        <td class="text-muted-foreground w-12 px-4 py-3 text-center font-medium">{{ msg.index }}</td>
                        <td class="max-w-[200px] truncate px-4 py-3">{{ msg.content_preview }}</td>
                        <td class="px-4 py-3 text-right capitalize">
                            <template v-if="msg.tone || msg.sentiment">{{ msg.tone || msg.sentiment }}</template>
                            <span v-else class="text-muted-foreground italic">{{ t('agents.chatlog.pulse.waiting') }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
