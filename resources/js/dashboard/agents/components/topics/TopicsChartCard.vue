<script setup lang="ts">
/**
 * The main card of the Topics page: topic counts over time (one line per
 * topic) or as a pie, next to the list of topic totals and a button that
 * opens the topic manager.
 */
import type { TopicTrends } from '@/dashboard/agents/api/topics';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import { useLocaleDate } from '@/shared/composables/useLocaleDate';
import { translucent } from '@/dashboard/agents/components/analytics/messageDimension';
import {
    ArcElement,
    CategoryScale,
    Chart as ChartJS,
    Tooltip as ChartTooltip,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
} from 'chart.js';
import { Info, Maximize2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Line, Pie } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';
import { TOPIC_COLORS, topicColor, topicLineOptions, topicPieOptions } from './topicCharts';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, ChartTooltip, Legend, Filler);

const props = defineProps<{
    topicsData: TopicTrends;
}>();

const emit = defineEmits<{
    showInfo: [];
    viewAll: [];
}>();

// Kept by the page so the chosen view survives the reloads after a topic change.
const activeView = defineModel<'graph' | 'pie'>('view', { required: true });

const { t } = useI18n();
const { formatMonthDay } = useLocaleDate();

const lineChartData = computed(() => {
    const datasets = props.topicsData.topics_by_date.map((topic, index) => {
        const color = topicColor(index);

        return {
            label: topic.topic_name,
            data: topic.counts.map((d) => d.count),
            borderColor: color,
            backgroundColor: translucent(color),
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
        };
    });

    // Every topic covers the same days, so the first one gives the labels.
    const firstTopic = props.topicsData.topics_by_date[0];
    const labels = firstTopic ? firstTopic.counts.map((d) => formatMonthDay(d.date)) : [];

    return {
        labels,
        datasets,
    };
});

const pieChartData = computed(() => {
    const totals = props.topicsData.total_topics;

    return {
        labels: totals.map((topic) => topic.name),
        datasets: [
            {
                data: totals.map((topic) => topic.count),
                backgroundColor: TOPIC_COLORS,
                borderColor: '#fff',
                borderWidth: 2,
            },
        ],
    };
});

const pieChartOptions = topicPieOptions(() => props.topicsData.total_topics.reduce((sum, topic) => sum + topic.count, 0));
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                    <CardTitle>{{ t('agents.topics_index.chart_title') }}</CardTitle>
                    <button
                        @click="emit('showInfo')"
                        :aria-label="t('agents.topics_index.info_title')"
                        class="text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <Info class="h-4 w-4 cursor-pointer" />
                    </button>
                </div>
                <Tabs v-model="activeView" class="w-auto">
                    <TabsList>
                        <TabsTrigger value="graph" @click="activeView = 'graph'">{{ t('agents.topics_index.graph') }}</TabsTrigger>
                        <TabsTrigger value="pie" @click="activeView = 'pie'">{{ t('agents.topics_index.pie_chart') }}</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>
        </CardHeader>
        <CardContent>
            <div class="flex flex-col gap-6 lg:flex-row">
                <!-- Chart Area -->
                <div class="flex-1">
                    <!-- Line Chart -->
                    <div v-if="activeView === 'graph'" class="h-96">
                        <Line :data="lineChartData" :options="topicLineOptions" />
                    </div>

                    <!-- Pie Chart -->
                    <div v-else class="flex h-96 items-center justify-center">
                        <div class="w-full max-w-md">
                            <Pie :data="pieChartData" :options="pieChartOptions" />
                        </div>
                    </div>
                </div>

                <!-- Topics Sidebar -->
                <div class="w-full border-t pt-6 lg:w-80 lg:border-s lg:border-t-0 lg:ps-6 lg:pt-0">
                    <div class="flex h-96 flex-col space-y-4">
                        <!-- Total Topics -->
                        <div>
                            <div class="text-3xl font-bold">{{ topicsData.total_count }}</div>
                            <div class="text-muted-foreground text-sm">{{ t('agents.topics_index.total_topics') }}</div>
                        </div>

                        <!-- Topics List -->
                        <div v-if="topicsData.total_topics.length === 0" class="text-muted-foreground flex flex-1 items-center justify-center">
                            <p>{{ t('agents.topics_index.no_data') }}</p>
                        </div>
                        <div v-else class="flex-1 space-y-3 overflow-y-auto">
                            <div v-for="(topic, index) in topicsData.total_topics" :key="topic.id" class="flex items-center justify-between">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="h-3 w-3 flex-shrink-0 rounded-full" :style="{ backgroundColor: topicColor(index) }"></div>
                                    <span class="truncate text-sm font-medium">{{ topic.name }}</span>
                                </div>
                                <span class="ms-2 text-sm font-semibold">{{ topic.count }}</span>
                            </div>
                        </div>

                        <!-- View All Button at Bottom -->
                        <div class="border-t pt-2">
                            <Button variant="outline" size="sm" class="w-full" @click="emit('viewAll')">
                                <span class="flex items-center justify-center gap-2">
                                    {{ t('agents.topics_index.view_all') }}
                                    <Maximize2 class="h-4 w-4" />
                                </span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
