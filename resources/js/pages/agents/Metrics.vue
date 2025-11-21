<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Sparkles, Zap, Cpu, LoaderCircle, ThumbsUp, MessageSquare, Timer, Activity } from 'lucide-vue-next';
import axios from 'axios';

interface Props {
    agent: any
}

const props = defineProps<Props>();

const loading = ref(true);
const metrics = ref<any>(null);
const error = ref<string | null>(null);

const satisfactionRate = computed(() => {
    if (!metrics.value) return 0;
    const total = metrics.value.totalPositiveFeedback + metrics.value.totalNegativeFeedback;
    if (total === 0) return 0;
    return ((metrics.value.totalPositiveFeedback / total) * 100).toFixed(1);
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
        const response = await axios.get(route('agents.metrics.data', props.agent.id));
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
    <AgentLayout :agent="agent" title="Metrics" description="Engagement and performance metrics for your agent">
        <template #header-icon>
            <Sparkles class="h-5 w-5" />
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-12">
            <Card class="max-w-md">
                <CardContent class="pt-6">
                    <div class="text-center space-y-2">
                        <p class="text-destructive font-medium">{{ error }}</p>
                        <button
                            @click="fetchMetrics"
                            class="text-sm text-muted-foreground hover:text-foreground underline"
                        >
                            Try again
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Metrics Content -->
        <div v-else-if="metrics" class="space-y-6">
            <!-- Quick Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 flex items-center justify-center flex-shrink-0">
                                <MessageSquare class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Avg Messages/Conv</p>
                                <p class="text-xl font-bold">{{ metrics.avgMessagesPerConversation }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-green-500/10 dark:bg-green-500/20 flex items-center justify-center flex-shrink-0">
                                <ThumbsUp class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Satisfaction Rate</p>
                                <p class="text-xl font-bold">{{ satisfactionRate }}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-purple-500/10 dark:bg-purple-500/20 flex items-center justify-center flex-shrink-0">
                                <Timer class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Avg Response Time</p>
                                <p class="text-xl font-bold">{{ metrics.avgResponseTime }}s</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-orange-500/10 dark:bg-orange-500/20 flex items-center justify-center flex-shrink-0">
                                <Activity class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Unique Sessions</p>
                                <p class="text-xl font-bold">{{ metrics.uniqueSessions.toLocaleString() }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Engagement & Performance Metrics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Engagement Metrics -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Sparkles class="h-5 w-5" />
                            Engagement Metrics
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">With Feedback</p>
                                <p class="text-2xl font-bold">{{ metrics.conversationsWithFeedback }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">Positive</p>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ metrics.totalPositiveFeedback }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">Negative</p>
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ metrics.totalNegativeFeedback }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">Total Messages</p>
                                <p class="text-2xl font-bold">{{ metrics.totalMessages.toLocaleString() }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Performance Metrics -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Zap class="h-5 w-5" />
                            Performance Metrics
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">First Response</p>
                                <p class="text-2xl font-bold">{{ formatDuration(metrics.avgFirstResponseTime) }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">Avg Duration</p>
                                <p class="text-2xl font-bold">{{ formatDuration(metrics.avgConversationDuration) }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">Tokens/Message</p>
                                <p class="text-2xl font-bold">{{ metrics.avgTokensPerMessage.toLocaleString() }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-muted-foreground">Total Tokens</p>
                                <p class="text-2xl font-bold">{{ metrics.totalTokens.toLocaleString() }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Model Usage -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Cpu class="h-5 w-5" />
                        Model Usage
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!metrics.modelUsage.length" class="text-center py-8 text-muted-foreground">
                        No model usage data available
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="model in metrics.modelUsage" :key="model.name" class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-muted/50 border flex items-center justify-center flex-shrink-0">
                                <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-6 w-6" />
                                <Cpu v-else class="h-6 w-6 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">{{ model.name }}</p>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{{ model.count }} uses</span>
                                    <span v-if="model.feedback.positive > 0">
                                        • <ThumbsUp class="inline h-3 w-3" /> {{ model.feedback.positive }}
                                    </span>
                                </div>
                            </div>
                            <Badge variant="secondary">{{ model.percentage }}%</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>

