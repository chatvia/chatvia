<script lang="ts" setup>
/**
 * Agent analytics overview: headline numbers, conversations over time (daily
 * and hourly), chats by country and performance/usage for a date range.
 * useAgentStats owns the loading and the date range; the cards only render.
 */
import AnalyticsErrorState from '@/dashboard/agents/components/analytics/AnalyticsErrorState.vue';
import ConversationTrendCard from '@/dashboard/agents/components/analytics/overview/ConversationTrendCard.vue';
import CountryUsageCard from '@/dashboard/agents/components/analytics/overview/CountryUsageCard.vue';
import PerformanceCard from '@/dashboard/agents/components/analytics/overview/PerformanceCard.vue';
import StatTile from '@/dashboard/agents/components/analytics/overview/StatTile.vue';
import { useAgentStats } from '@/dashboard/agents/components/analytics/overview/useAgentStats';
import DateRangePicker from '@/dashboard/agents/components/DateRangePicker.vue';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { LoaderCircle, MessageCircle, MessageSquare, ThumbsDown, ThumbsUp } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

const { t } = useI18n();

const { loading, isRefreshing, analytics, error, dateRange, isDatePickerDisabled, fetchAnalytics } = useAgentStats(() => props.agent.external_id);

// Daily or hourly view of the conversations card.
const activeChartTab = ref('daily');
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.analytics.title')" :description="t('agents.analytics.description')">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
        </div>

        <AnalyticsErrorState v-else-if="error" :message="error" :retry-label="t('agents.analytics.try_again')" @retry="fetchAnalytics" />

        <!-- Analytics Content -->
        <div v-else-if="analytics">
            <!-- Date Range Picker -->
            <div class="relative mb-6">
                <div class="flex items-center gap-3">
                    <DateRangePicker v-model="dateRange" :first-data-date="analytics?.first_data_date" :disabled="isDatePickerDisabled" />
                    <!-- Refreshing Indicator -->
                    <div v-if="isRefreshing" class="text-muted-foreground flex items-center gap-2 text-sm">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        <span>{{ t('agents.analytics.updating') }}</span>
                    </div>
                    <div v-if="isDatePickerDisabled" class="text-muted-foreground text-sm">{{ t('agents.analytics.no_data_yet') }}</div>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    :icon="MessageCircle"
                    :label="t('agents.analytics.total_conversations')"
                    :value="(analytics?.total_conversations ?? 0).toLocaleString()"
                />
                <StatTile
                    :icon="MessageSquare"
                    :label="t('agents.analytics.total_messages')"
                    :value="(analytics?.total_messages ?? 0).toLocaleString()"
                />
                <StatTile :icon="ThumbsUp" :label="t('agents.analytics.thumbs_up')" :value="analytics?.feedback?.positive ?? 0" />
                <StatTile :icon="ThumbsDown" :label="t('agents.analytics.thumbs_down')" :value="analytics?.feedback?.negative ?? 0" />
            </div>

            <ConversationTrendCard v-model:tab="activeChartTab" :stats="analytics" :date-range="dateRange" />

            <CountryUsageCard :countries="analytics?.country_usage" />

            <!-- Performance & resource usage -->
            <PerformanceCard :stats="analytics" />
        </div>
    </AgentLayout>
</template>
