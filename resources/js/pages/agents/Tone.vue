<script setup lang="ts">
/**
 * Tone analytics: how visitors sounded (neutral, frustrated, urgent, confused,
 * satisfied) per day and in total. This page only describes the tone buckets;
 * MessageDimensionAnalytics does the gating, loading and charts it shares
 * with the Sentiment page.
 */
import { fetchToneBreakdown, type ToneBucket } from '@/api/analytics';
import MessageDimensionAnalytics from '@/components/analytics/MessageDimensionAnalytics.vue';
import type {
    DimensionBreakdown,
    DimensionCopy,
    DimensionLineSeries,
    DimensionPieSlice,
    DimensionPointStyle,
    DimensionSummaryCard,
} from '@/components/analytics/messageDimension';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { MessageCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    // Per-agent on/off switch (Setup → Settings). Independent of the plan
    // capability: the plan must allow it AND the owner must not have
    // switched it off.
    enabled: boolean;
    // Master switch: when the chat log is off there are no stored messages to
    // analyze, so analytics can't be enabled until it's turned back on.
    chatlogEnabled: boolean;
}

const props = defineProps<Props>();

const { t } = useI18n();

const loadTone = async (): Promise<DimensionBreakdown<ToneBucket>> => {
    const response = await fetchToneBreakdown(props.agent.external_id);

    return { byDate: response.toneByDate, totals: response.totalTones };
};

const copy = computed<DimensionCopy>(() => ({
    lockedDescription: t('agents.tone.locked_description'),
    disabledTitle: t('agents.tone.disabled_title'),
    disabledDescription: t('agents.tone.disabled_description'),
    blockedReason: t('agents.tone.blocked_reason'),
    liveAnalytics: t('agents.tone.live_analytics'),
    loadError: t('agents.tone.load_error'),
    tryAgain: t('agents.tone.try_again'),
    chartTitle: t('agents.tone.tone_distribution'),
    graph: t('agents.tone.graph'),
    pieChart: t('agents.tone.pie_chart'),
}));

const POINT: DimensionPointStyle = { pointRadius: 3 };

const lineSeries = computed<DimensionLineSeries<ToneBucket>[]>(() => [
    { key: 'neutral', label: t('agents.tone.neutral_label'), color: 'rgb(156, 163, 175)', point: POINT },
    { key: 'frustrated', label: t('agents.tone.frustrated_label'), color: 'rgb(239, 68, 68)', point: POINT },
    { key: 'urgent', label: t('agents.tone.urgent_label'), color: 'rgb(251, 146, 60)', point: POINT },
    { key: 'confused', label: t('agents.tone.confused_label'), color: 'rgb(168, 85, 247)', point: POINT },
    { key: 'satisfied', label: t('agents.tone.satisfied_label'), color: 'rgb(74, 222, 128)', point: POINT },
]);

const pieSlices = computed<DimensionPieSlice<ToneBucket>[]>(() => [
    { key: 'neutral', label: t('agents.tone.neutral'), color: 'rgb(156, 163, 175)' },
    { key: 'frustrated', label: t('agents.tone.frustrated'), color: 'rgb(239, 68, 68)' },
    { key: 'urgent', label: t('agents.tone.urgent'), color: 'rgb(251, 146, 60)' },
    { key: 'confused', label: t('agents.tone.confused'), color: 'rgb(168, 85, 247)' },
    { key: 'satisfied', label: t('agents.tone.satisfied'), color: 'rgb(74, 222, 128)' },
    { key: 'unspecified', label: t('agents.tone.unspecified'), color: 'rgb(203, 213, 225)' },
]);

const summaryCards = computed<DimensionSummaryCard<ToneBucket>[]>(() => [
    { key: 'neutral', label: t('agents.tone.neutral'), badgeVariant: 'secondary' },
    { key: 'frustrated', label: t('agents.tone.frustrated'), badgeClass: 'bg-red-100 text-red-700 hover:bg-red-100' },
    { key: 'satisfied', label: t('agents.tone.satisfied'), badgeClass: 'bg-green-100 text-green-700 hover:bg-green-100' },
    { key: 'unspecified', label: t('agents.tone.unspecified'), badgeVariant: 'outline' },
]);
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.tone.title')" :description="t('agents.tone.description')">
        <template #header-icon>
            <MessageCircle class="h-5 w-5" />
        </template>

        <MessageDimensionAnalytics
            :agent="agent"
            :enabled="enabled"
            :chatlog-enabled="chatlogEnabled"
            feature="tone"
            :load="loadTone"
            :copy="copy"
            :line-series="lineSeries"
            :pie-slices="pieSlices"
            :summary-cards="summaryCards"
        />
    </AgentLayout>
</template>
