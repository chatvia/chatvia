<script setup lang="ts">
/**
 * Sentiment analytics: how positive or negative visitors were per day and in
 * total. This page only describes the sentiment buckets;
 * MessageDimensionAnalytics does the gating, loading and charts it shares
 * with the Tone page.
 */
import { fetchSentimentBreakdown, type SentimentBucket } from '@/dashboard/agents/api/analytics';
import MessageDimensionAnalytics from '@/dashboard/agents/components/analytics/MessageDimensionAnalytics.vue';
import type {
    DimensionBreakdown,
    DimensionCopy,
    DimensionLineSeries,
    DimensionPieSlice,
    DimensionPointStyle,
    DimensionSummaryCard,
} from '@/dashboard/agents/components/analytics/messageDimension';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    // Per-agent on/off switch (Setup → Settings).
    enabled: boolean;
    // Master switch: when the chat log is off there are no stored messages to
    // analyze, so analytics can't be enabled until it's turned back on.
    chatlogEnabled: boolean;
}

const props = defineProps<Props>();

const { t } = useI18n();

const loadSentiment = async (): Promise<DimensionBreakdown<SentimentBucket>> => {
    const response = await fetchSentimentBreakdown(props.agent.external_id);

    return { byDate: response.sentimentByDate, totals: response.totalSentiments };
};

const copy = computed<DimensionCopy>(() => ({
    disabledTitle: t('agents.sentiment.disabled_title'),
    disabledDescription: t('agents.sentiment.disabled_description'),
    blockedReason: t('agents.sentiment.blocked_reason'),
    liveAnalytics: t('agents.sentiment.live_analytics'),
    loadError: t('agents.sentiment.load_error'),
    tryAgain: t('agents.sentiment.try_again'),
    chartTitle: t('agents.sentiment.sentiment'),
    graph: t('agents.sentiment.graph'),
    pieChart: t('agents.sentiment.pie_chart'),
}));

const POINT: DimensionPointStyle = { pointRadius: 4, pointBorderColor: '#fff', pointBorderWidth: 2 };

const lineSeries = computed<DimensionLineSeries<SentimentBucket>[]>(() => [
    { key: 'positive', label: t('agents.sentiment.positive'), color: 'rgb(96, 165, 250)', point: POINT },
    { key: 'neutral', label: t('agents.sentiment.neutral'), color: 'rgb(71, 85, 105)', point: POINT },
    { key: 'negative', label: t('agents.sentiment.negative'), color: 'rgb(248, 113, 113)', point: POINT },
    { key: 'unspecified', label: t('agents.sentiment.unspecified'), color: 'rgb(203, 213, 225)', point: POINT },
]);

const pieSlices = computed<DimensionPieSlice<SentimentBucket>[]>(() => [
    { key: 'neutral', label: t('agents.sentiment.neutral'), color: 'rgb(71, 85, 105)' },
    { key: 'positive', label: t('agents.sentiment.positive'), color: 'rgb(96, 165, 250)' },
    { key: 'negative', label: t('agents.sentiment.negative'), color: 'rgb(248, 113, 113)' },
    { key: 'unspecified', label: t('agents.sentiment.unspecified'), color: 'rgb(203, 213, 225)' },
]);

const summaryCards = computed<DimensionSummaryCard<SentimentBucket>[]>(() => [
    { key: 'positive', label: t('agents.sentiment.positive'), badgeClass: 'bg-blue-100 text-blue-700 hover:bg-blue-100' },
    { key: 'neutral', label: t('agents.sentiment.neutral'), badgeVariant: 'secondary' },
    { key: 'negative', label: t('agents.sentiment.negative'), badgeClass: 'bg-red-100 text-red-700 hover:bg-red-100' },
    { key: 'unspecified', label: t('agents.sentiment.unspecified'), badgeVariant: 'outline' },
]);
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sentiment.title')" :description="t('agents.sentiment.description')">
        <MessageDimensionAnalytics
            :agent="agent"
            :enabled="enabled"
            :chatlog-enabled="chatlogEnabled"
            feature="sentiment"
            :load="loadSentiment"
            :copy="copy"
            :line-series="lineSeries"
            :pie-slices="pieSlices"
            :summary-cards="summaryCards"
        />
    </AgentLayout>
</template>
