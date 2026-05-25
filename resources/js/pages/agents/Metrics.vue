<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AgentLayout from '@/layouts/AgentLayout.vue';
import axios from 'axios';
import { Award, BarChart3, Coins, Cpu, LoaderCircle, MessageSquare, Sparkles, Target, ThumbsUp, Zap } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

const props = defineProps<Props>();

const loading = ref(true);
const metrics = ref<Domain.Agents.Data.Stats.AgentMetricsData | null>(null);
const error = ref<string | null>(null);

const satisfactionRate = computed(() => {
    if (!metrics.value) return '0';
    const total = metrics.value.totalPositiveFeedback + metrics.value.totalNegativeFeedback;
    if (total === 0) return '0';
    return ((metrics.value.totalPositiveFeedback / total) * 100).toFixed(1);
});

const qualityScore = computed(() => {
    if (!metrics.value) return '0';
    // Calculate quality score based on satisfaction rate and avg messages per conversation
    const satisfaction = parseFloat(satisfactionRate.value);
    const engagementScore = Math.min((metrics.value.avgMessagesPerConversation ?? 0) * 10, 100);
    return (satisfaction * 0.7 + engagementScore * 0.3).toFixed(1);
});

const tokenEfficiency = computed(() => {
    if (!metrics.value || !metrics.value.avgTokensPerMessage) return 'N/A';
    const tokens = metrics.value.avgTokensPerMessage;
    if (tokens < 500) return 'Excellent';
    if (tokens < 1000) return 'Good';
    if (tokens < 1500) return 'Fair';
    return 'Needs Review';
});

const performanceRating = computed(() => {
    if (!metrics.value || !metrics.value.avgResponseTime) return 'N/A';
    const time = metrics.value.avgResponseTime;
    if (time < 2) return 'Excellent';
    if (time < 5) return 'Good';
    if (time < 10) return 'Fair';
    return 'Needs Improvement';
});

const formatDuration = (seconds: number | null): string => {
    if (!seconds) return 'N/A';
    const minutes = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    }
    return `${secs}s`;
};

const fetchMetrics = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('agents.metrics.data', props.agent.external_id));
        metrics.value = response.data;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load metrics';
        console.error('Error fetching metrics:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchMetrics();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Performance & Quality" description="Monitor your agent's performance, quality, and resource efficiency">
        <template #header-icon>
            <Sparkles class="h-5 w-5" />
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-12">
            <Card class="max-w-md">
                <CardContent class="pt-6">
                    <div class="space-y-2 text-center">
                        <p class="text-destructive font-medium">{{ error }}</p>
                        <button @click="fetchMetrics" class="text-muted-foreground hover:text-foreground text-sm underline">Try again</button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Metrics Content -->
        <div v-else-if="metrics" class="space-y-6">
            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Quality Score -->
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="bg-muted flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg">
                                <Award class="text-muted-foreground h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-muted-foreground mb-0.5 text-xs">Quality Score</p>
                                <div class="flex items-center gap-2">
                                    <p class="text-xl font-bold">{{ qualityScore }}%</p>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ parseFloat(qualityScore) >= 80 ? 'Excellent' : parseFloat(qualityScore) >= 60 ? 'Good' : 'Fair' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Satisfaction Rate -->
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="bg-muted flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg">
                                <ThumbsUp class="text-muted-foreground h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-muted-foreground mb-0.5 text-xs">Satisfaction Rate</p>
                                <p class="text-xl font-bold">{{ satisfactionRate }}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Response Speed -->
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="bg-muted flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg">
                                <Zap class="text-muted-foreground h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-muted-foreground mb-0.5 text-xs">Response Speed</p>
                                <div class="flex items-center gap-2">
                                    <p class="text-xl font-bold">{{ metrics.avgResponseTime ?? 0 }}s</p>
                                    <Badge variant="secondary" class="text-xs">{{ performanceRating }}</Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Conversation Depth -->
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="bg-muted flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg">
                                <BarChart3 class="text-muted-foreground h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-muted-foreground mb-0.5 text-xs">Avg Depth</p>
                                <p class="text-xl font-bold">{{ Number(metrics.avgMessagesPerConversation ?? 0).toFixed(1) }} msgs</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Performance Metrics Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Zap class="h-5 w-5" />
                        Performance Metrics
                    </CardTitle>
                    <CardDescription>Speed and response time analysis</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">First Response Time</p>
                                <Badge variant="outline">{{ performanceRating }}</Badge>
                            </div>
                            <p class="text-3xl font-bold">{{ formatDuration(metrics.avgFirstResponseTime) }}</p>
                            <p class="text-muted-foreground text-xs">Time to send first message</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">Avg Response Time</p>
                            </div>
                            <p class="text-3xl font-bold">{{ metrics.avgResponseTime ?? 0 }}s</p>
                            <p class="text-muted-foreground text-xs">Average per message</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">Avg Session Duration</p>
                            </div>
                            <p class="text-3xl font-bold">{{ formatDuration(metrics.avgConversationDuration) }}</p>
                            <p class="text-muted-foreground text-xs">Total conversation time</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quality & Engagement Metrics -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Target class="h-5 w-5" />
                        Quality & Engagement
                    </CardTitle>
                    <CardDescription>User satisfaction and interaction quality</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Satisfaction Breakdown -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-sm font-medium">User Satisfaction</p>
                                <span class="text-sm font-bold">{{ satisfactionRate }}%</span>
                            </div>
                            <Progress :model-value="parseFloat(satisfactionRate)" class="h-2" />
                            <div class="text-muted-foreground mt-2 flex items-center justify-between text-xs">
                                <span>{{ metrics.totalPositiveFeedback ?? 0 }} positive</span>
                                <span>{{ metrics.totalNegativeFeedback ?? 0 }} negative</span>
                            </div>
                        </div>

                        <!-- Engagement Stats -->
                        <div class="grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-3">
                            <div>
                                <p class="text-muted-foreground mb-1 text-sm">Conversations with Feedback</p>
                                <p class="text-2xl font-bold">{{ metrics.conversationsWithFeedback ?? 0 }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-1 text-sm">Avg Messages per Chat</p>
                                <p class="text-2xl font-bold">{{ Number(metrics.avgMessagesPerConversation ?? 0).toFixed(1) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-1 text-sm">Unique Sessions</p>
                                <p class="text-2xl font-bold">{{ (metrics.uniqueSessions ?? 0).toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Resource Usage Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Coins class="h-5 w-5" />
                        Resource Usage
                    </CardTitle>
                    <CardDescription>Token consumption and efficiency metrics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">Total Tokens Used</p>
                            </div>
                            <p class="text-3xl font-bold">{{ (metrics.totalTokens ?? 0).toLocaleString() }}</p>
                            <p class="text-muted-foreground text-xs">Across all conversations</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">Avg Tokens per Message</p>
                                <Badge variant="outline">{{ tokenEfficiency }}</Badge>
                            </div>
                            <p class="text-3xl font-bold">{{ Math.round(metrics.avgTokensPerMessage ?? 0).toLocaleString() }}</p>
                            <p class="text-muted-foreground text-xs">Token efficiency rating</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">Total Messages</p>
                            </div>
                            <p class="text-3xl font-bold">{{ (metrics.totalMessages ?? 0).toLocaleString() }}</p>
                            <p class="text-muted-foreground text-xs">Assistant messages sent</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Model Usage - Only for knowledge_base agents -->
            <Card v-if="agent.type === 'knowledge_base'">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Cpu class="h-5 w-5" />
                        Model Performance
                    </CardTitle>
                    <CardDescription>AI model usage distribution and effectiveness</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!metrics.modelUsage?.length" class="text-muted-foreground py-8 text-center">No model usage data available</div>
                    <div v-else class="space-y-4">
                        <div v-for="model in metrics.modelUsage" :key="model.name" class="flex items-center gap-4 rounded-lg border p-3">
                            <div class="bg-muted/50 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full border">
                                <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-7 w-7" />
                                <Cpu v-else class="text-muted-foreground h-7 w-7" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex items-center gap-2">
                                    <p class="truncate font-medium">{{ model.name }}</p>
                                    <Badge variant="secondary" class="ml-auto">{{ model.percentage }}%</Badge>
                                </div>
                                <div class="text-muted-foreground flex items-center gap-4 text-xs">
                                    <span class="flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        {{ model.count }} uses
                                    </span>
                                    <span v-if="model.feedback.positive > 0" class="flex items-center gap-1">
                                        <ThumbsUp class="h-3 w-3" />
                                        {{ model.feedback.positive }} positive
                                    </span>
                                </div>
                                <Progress :model-value="parseFloat(model.percentage)" class="mt-2 h-1.5" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>
