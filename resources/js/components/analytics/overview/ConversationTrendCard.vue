<script setup lang="ts">
/** "Conversations started" card of the analytics overview, with a daily and an hourly view. */
import type { AgentStats } from '@/api/analytics';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TrendingUp } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import DailyConversationsChart from './DailyConversationsChart.vue';
import HourlyActivityPanel from './HourlyActivityPanel.vue';
import type { StatsDateRange } from './useAgentStats';

defineProps<{
    stats: AgentStats;
    dateRange: StatsDateRange;
}>();

const { t } = useI18n();

// Kept by the page so the chosen view survives a reload of the stats.
const activeChartTab = defineModel<string>('tab', { required: true });
</script>

<template>
    <Card class="mb-6">
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5" />
                    {{ t('agents.analytics.conversations_started') }}
                </CardTitle>
                <Tabs v-model="activeChartTab" default-value="daily">
                    <TabsList class="grid grid-cols-2">
                        <TabsTrigger value="daily">{{ t('agents.analytics.daily_view') }}</TabsTrigger>
                        <TabsTrigger value="hourly">{{ t('agents.analytics.hourly_view') }}</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="activeChartTab === 'daily'">
                <DailyConversationsChart :stats="stats" :date-range="dateRange" />
            </div>

            <div v-else-if="activeChartTab === 'hourly'">
                <HourlyActivityPanel :stats="stats" />
            </div>
        </CardContent>
    </Card>
</template>
