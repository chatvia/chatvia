<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BarChart3, MessageSquare, Users, Clock, ThumbsUp, ThumbsDown, BarChart, Cpu, MessageCircle } from 'lucide-vue-next';
import { useTimeAgo } from '@/composables/useTimeAgo';

interface Props {
    agent: any
    analytics: any
}

const props = defineProps<Props>();

// Use time ago composable
const { formatTimeAgo } = useTimeAgo();

// Model usage sorting
const modelSortBy = ref<'usage' | 'feedback'>('usage');

// Chart time period filtering
const chartTimePeriod = ref<'7days' | '14days' | '30days'>('7days');
const chartDataFromApi = ref<any[]>([]);
const isLoadingChartData = ref(false);

// Fetch chart data from API
const fetchChartData = async (days: number) => {
    isLoadingChartData.value = true;
    try {
        const response = await axios.get(route('agents.stats.chart-data', props.agent.id), {
            params: { days }
        });
        chartDataFromApi.value = response.data.data;
    } catch (error) {
        console.error('Failed to fetch chart data:', error);
        chartDataFromApi.value = [];
    } finally {
        isLoadingChartData.value = false;
    }
};

// Watch for time period changes and fetch data
watch(chartTimePeriod, (newPeriod) => {
    const daysMap = {
        '7days': 7,
        '14days': 14,
        '30days': 30
    };
    fetchChartData(daysMap[newPeriod]);
}, { immediate: true });

// Process chart data for display
const chartData = computed(() => {
    if (!chartDataFromApi.value || chartDataFromApi.value.length === 0) {
        return [];
    }
    
    return chartDataFromApi.value.map((item: any) => {
        const date = new Date(item.date);
        return {
            date: item.date,
            day: date.toLocaleDateString('en-US', { weekday: 'short' }),
            dayMonth: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
            fullDate: date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
            messages: item.messages || 0,
        };
    });
});

const maxMessages = computed(() => {
    if (!chartData.value.length) return 100;
    return Math.max(...chartData.value.map((day: any) => day.messages));
});

const chartTitle = computed(() => {
    const periodMap = {
        '7days': 'Last 7 Days',
        '14days': 'Last 14 Days',
        '30days': 'Last 30 Days'
    };
    return `Messages Over Time - ${periodMap[chartTimePeriod.value]}`;
});

// Use real analytics data
const stats = computed(() => ({
    totalMessages: props.analytics?.totalMessages || 0,
    totalConversations: props.analytics?.totalConversations || 0,
    messagesLastWeek: props.analytics?.messagesLastWeek || 0,
    uniqueUsers: props.analytics?.uniqueUsers || 0,
    newUsersLastWeek: props.analytics?.newUsersLastWeek || 0,
    avgResponseTime: props.analytics?.avgResponseTime || '0s',
    feedback: props.analytics?.feedback || { positive: 0, negative: 0, total: 0 },
    topKnowledge: props.analytics?.topKnowledge || [],
    modelUsage: props.analytics?.modelUsage || [],
    countryUsage: props.analytics?.countryUsage || []
}));

// Get flag path for country
const getFlagPath = (countryCode: string): string => {
    return `/images/flags/${countryCode.toLowerCase()}.svg`;
};

// Calculate feedback sentiment
const feedbackSentiment = computed(() => {
    const { positive, negative, total } = stats.value.feedback;
    
    if (total === 0) {
        return {
            sentiment: 'neutral',
            message: 'No feedback yet',
            color: 'text-muted-foreground'
        };
    }
    
    const positiveDiff = positive - negative;
    
    if (positiveDiff > 0) {
        return {
            sentiment: 'positive',
            message: `${positiveDiff} more positive`,
            color: 'text-green-600 dark:text-green-500'
        };
    } else if (positiveDiff < 0) {
        return {
            sentiment: 'negative',
            message: `${Math.abs(positiveDiff)} more negative`,
            color: 'text-red-600 dark:text-red-500'
        };
    } else {
        return {
            sentiment: 'balanced',
            message: 'Balanced feedback',
            color: 'text-blue-600 dark:text-blue-500'
        };
    }
});

// Sorted model usage
const sortedModelUsage = computed(() => {
    if (!stats.value.modelUsage || stats.value.modelUsage.length === 0) {
        return [];
    }
    
    const models = [...stats.value.modelUsage];
    
    if (modelSortBy.value === 'usage') {
        // Sort by usage count (descending)
        return models.sort((a, b) => b.count - a.count);
    } else {
        // Sort by positive feedback (descending), then by usage count
        return models.sort((a, b) => {
            const aPositive = a.feedback?.positive || 0;
            const bPositive = b.feedback?.positive || 0;
            
            if (aPositive !== bPositive) {
                return bPositive - aPositive;
            }
            
            // If positive feedback is the same, sort by usage count
            return b.count - a.count;
        });
    }
});
</script>

<template>
    <AgentLayout :agent="agent" title="Agent Statistics">
        <!-- Status indicator -->
        <div class="flex items-center gap-2 mb-6 text-xs text-muted-foreground">
            <div class="flex items-center gap-1">
                <div class="w-2 h-2 bg-amber-500 rounded-full"></div>
                <span>Statistics are 10 minutes delayed</span>
            </div>
            <span>•</span>
            <span>Last updated: {{ formatTimeAgo(props.analytics?.lastUpdated) }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-full">
            <!-- Agent Statistics Card -->
            <Card class="overflow-hidden">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart3 class="h-5 w-5" />
                        Agent Statistics
                    </CardTitle>
                    <CardDescription>
                        Core performance metrics for your agent
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border bg-card p-4">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <MessageCircle class="h-4 w-4" />
                                    <span class="text-sm font-medium">Total Conversations</span>
                                </div>
                                <div class="mt-2 text-2xl font-bold">{{ stats.totalConversations.toLocaleString() }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Number of conversations with your agent
                                </div>
                            </div>

                            <div class="rounded-lg border bg-card p-4">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <MessageSquare class="h-4 w-4" />
                                    <span class="text-sm font-medium">Total Messages</span>
                                </div>
                                <div class="mt-2 text-2xl font-bold">{{ stats.totalMessages.toLocaleString() }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Since you started using the agent
                                </div>
                            </div>

                            <div v-if="agent.type !== 'widget'" class="rounded-lg border bg-card p-4">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <Users class="h-4 w-4" />
                                    <span class="text-sm font-medium">Members</span>
                                </div>
                                <div class="mt-2 text-2xl font-bold">{{ stats.uniqueUsers }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Members who have interacted with your agent
                                </div>
                            </div>

                            <div class="rounded-lg border bg-card p-4">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <Clock class="h-4 w-4" />
                                    <span class="text-sm font-medium">Avg. Response Time</span>
                                </div>
                                <div class="mt-2 text-2xl font-bold">{{ stats.avgResponseTime }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Time taken to respond to a message
                                </div>
                            </div>

                            <div class="rounded-lg border bg-card p-4">
                                <div class="flex items-center gap-2 text-muted-foreground mb-3">
                                    <span class="text-sm font-medium">Feedback</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-1">
                                            <ThumbsUp class="h-4 w-4 text-green-600 dark:text-green-500" />
                                            <span class="text-xl font-bold">{{ stats.feedback.positive }}</span>
                                        </div>
                                        <span class="text-muted-foreground">/</span>
                                        <div class="flex items-center gap-1">
                                            <ThumbsDown class="h-4 w-4 text-red-600 dark:text-red-500" />
                                            <span class="text-xl font-bold">{{ stats.feedback.negative }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-xs" :class="feedbackSentiment.color">
                                    {{ feedbackSentiment.message }}
                                </div>
                            </div>
                        </div>

                        <!-- Most Accessed Knowledge -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium">Most Accessed Knowledge</h3>
                            <div class="rounded-lg border">
                                <div v-if="!stats.topKnowledge || stats.topKnowledge.length === 0"
                                     class="p-4 text-center text-muted-foreground">
                                    No data available yet
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="(knowledge, index) in stats.topKnowledge"
                                         :key="index"
                                         class="flex items-center justify-between p-4">
                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                                {{ index + 1 }}
                                            </div>
                                            <div class="flex flex-col min-w-0 flex-1">
                                                <span class="font-medium truncate">{{ knowledge.name }}</span>
                                                <span class="text-xs text-muted-foreground">{{ knowledge.type }}</span>
                                            </div>
                                        </div>
                                        <Badge variant="secondary" class="flex-shrink-0">{{ knowledge.accessed }} times</Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Usage Analytics Card -->
            <Card class="overflow-hidden">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart class="h-5 w-5" />
                        Usage Analytics
                    </CardTitle>
                    <CardDescription>
                        Usage patterns and model performance insights
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Usage Over Time Chart -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium">{{ chartTitle }}</h3>
                                <div class="flex items-center gap-1 rounded-lg border p-1">
                                    <button
                                        @click="chartTimePeriod = '7days'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                                            chartTimePeriod === '7days'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        ]"
                                    >
                                        7 Days
                                    </button>
                                    <button
                                        @click="chartTimePeriod = '14days'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                                            chartTimePeriod === '14days'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        ]"
                                    >
                                        14 Days
                                    </button>
                                    <button
                                        @click="chartTimePeriod = '30days'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                                            chartTimePeriod === '30days'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        ]"
                                    >
                                        30 Days
                                    </button>
                                </div>
                            </div>
                            <div class="h-64 w-full overflow-hidden">
                                <div v-if="isLoadingChartData" class="flex h-full w-full items-center justify-center rounded-md border border-dashed border-muted-foreground/50 bg-muted/50">
                                    <div class="text-center text-muted-foreground">
                                        <BarChart class="mx-auto h-8 w-8 opacity-50 animate-pulse" />
                                        <p class="mt-2">Loading chart data...</p>
                                    </div>
                                </div>
                                <div v-else-if="!chartData.length" class="flex h-full w-full items-center justify-center rounded-md border border-dashed border-muted-foreground/50 bg-muted/50">
                                    <div class="text-center text-muted-foreground">
                                        <BarChart class="mx-auto h-8 w-8 opacity-50" />
                                        <p class="mt-2">No data available</p>
                                        <p class="text-xs">Data will appear as users interact with your agent</p>
                                    </div>
                                </div>
                                <div v-else class="h-full flex items-end gap-1">
                                    <div v-for="day in chartData" :key="day.date" class="flex flex-1 flex-col items-center gap-2 group relative">
                                        <div class="w-full rounded-t-sm cursor-pointer bg-primary"
                                             :style="{ height: `${day.messages === 0 ? 5 : Math.max(4, (day.messages / maxMessages) * 200)}px` }">
                                        </div>
                                        <!-- Hover tooltip -->
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none">
                                            {{ day.dayMonth }}: {{ day.messages }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Model Usage -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium">Model Usage</h3>
                                <div class="flex items-center gap-1 rounded-lg border p-1">
                                    <button
                                        @click="modelSortBy = 'usage'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                                            modelSortBy === 'usage'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        ]"
                                    >
                                        By Usage
                                    </button>
                                    <button
                                        @click="modelSortBy = 'feedback'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                                            modelSortBy === 'feedback'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        ]"
                                    >
                                        By Feedback
                                    </button>
                                </div>
                            </div>
                            <div class="rounded-lg border">
                                <div v-if="!stats.modelUsage || stats.modelUsage.length === 0"
                                     class="p-4 text-center text-muted-foreground">
                                    No model usage data available yet
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="model in sortedModelUsage"
                                         :key="model.name"
                                         class="p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                                <!-- Model Icon in Circle -->
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted/50 border flex-shrink-0">
                                                    <img
                                                        v-if="model.icon"
                                                        :src="`/images/llms/${model.icon}`"
                                                        :alt="model.name"
                                                        class="h-6 w-6 object-contain"
                                                    />
                                                    <Cpu v-else class="h-6 w-6 text-muted-foreground" />
                                                </div>
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <span class="font-medium truncate">{{ model.name }}</span>
                                                    <div class="flex items-center gap-3 mt-1">
                                                        <div v-if="model.feedback.positive > 0 || model.feedback.negative > 0" class="flex items-center gap-2 text-xs">
                                                            <div class="flex items-center gap-1">
                                                                <ThumbsUp class="h-3 w-3 text-green-600 dark:text-green-500" />
                                                                <span class="text-green-600 dark:text-green-500 font-medium">{{ model.feedback.positive }}</span>
                                                            </div>
                                                            <span class="text-muted-foreground">/</span>
                                                            <div class="flex items-center gap-1">
                                                                <ThumbsDown class="h-3 w-3 text-red-600 dark:text-red-500" />
                                                                <span class="text-red-600 dark:text-red-500 font-medium">{{ model.feedback.negative }}</span>
                                                            </div>
                                                        </div>
                                                        <span v-else class="text-xs text-muted-foreground">No feedback yet</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <div class="w-24 bg-muted rounded-full h-2">
                                                    <div class="bg-primary h-2 rounded-full transition-all duration-300"
                                                         :style="{ width: `${model.percentage}%` }"></div>
                                                </div>
                                                <span class="text-sm text-muted-foreground whitespace-nowrap">{{ model.percentage }}%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Countries -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium">Conversations by Country</h3>
                            </div>
                            <div class="rounded-lg border">
                                <div v-if="!stats.countryUsage || stats.countryUsage.length === 0"
                                     class="p-4 text-center text-muted-foreground">
                                    No country data available yet
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="country in stats.countryUsage"
                                         :key="country.country_code"
                                         class="p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                                <!-- Country Flag in Circle -->
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted/50 border flex-shrink-0 overflow-hidden">
                                                    <img
                                                        :src="getFlagPath(country.country_code)"
                                                        :alt="country.country_code"
                                                        class="w-full h-full object-cover"
                                                    />
                                                </div>
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <span class="font-medium truncate">{{ country.country_code }}</span>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span class="text-xs text-muted-foreground">{{ country.count }} {{ country.count === 1 ? 'conversation' : 'conversations' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <div class="w-24 bg-muted rounded-full h-2">
                                                    <div class="bg-primary h-2 rounded-full transition-all duration-300"
                                                         :style="{ width: `${country.percentage}%` }"></div>
                                                </div>
                                                <span class="text-sm text-muted-foreground whitespace-nowrap">{{ country.percentage }}%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>
