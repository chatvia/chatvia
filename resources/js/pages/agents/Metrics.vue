<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { 
    Sparkles, Zap, Cpu, LoaderCircle, ThumbsUp, MessageSquare, 
    Timer, Activity, TrendingUp, TrendingDown, Target, Clock,
    Coins, BarChart3, Award
} from 'lucide-vue-next';
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

const qualityScore = computed(() => {
    if (!metrics.value) return '0';
    // Calculate quality score based on satisfaction rate and avg messages per conversation
    const satisfaction = parseFloat(satisfactionRate.value);
    const engagementScore = Math.min(metrics.value.avgMessagesPerConversation * 10, 100);
    return ((satisfaction * 0.7 + engagementScore * 0.3)).toFixed(1);
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
    <AgentLayout :agent="agent" title="Performance & Quality" description="Monitor your agent's performance, quality, and resource efficiency">
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
            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Quality Score -->
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
                                <Award class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Quality Score</p>
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
                            <div class="h-10 w-10 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
                                <ThumbsUp class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Satisfaction Rate</p>
                                <p class="text-xl font-bold">{{ satisfactionRate }}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Response Speed -->
                <Card>
                    <CardContent class="!p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
                                <Zap class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Response Speed</p>
                                <div class="flex items-center gap-2">
                                    <p class="text-xl font-bold">{{ metrics.avgResponseTime }}s</p>
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
                            <div class="h-10 w-10 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
                                <BarChart3 class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-muted-foreground mb-0.5">Avg Depth</p>
                                <p class="text-xl font-bold">{{ Number(metrics.avgMessagesPerConversation).toFixed(1) }} msgs</p>
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">First Response Time</p>
                                <Badge variant="outline">{{ performanceRating }}</Badge>
                            </div>
                            <p class="text-3xl font-bold">{{ formatDuration(metrics.avgFirstResponseTime) }}</p>
                            <p class="text-xs text-muted-foreground">Time to send first message</p>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Avg Response Time</p>
                            </div>
                            <p class="text-3xl font-bold">{{ metrics.avgResponseTime }}s</p>
                            <p class="text-xs text-muted-foreground">Average per message</p>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Avg Session Duration</p>
                            </div>
                            <p class="text-3xl font-bold">{{ formatDuration(metrics.avgConversationDuration) }}</p>
                            <p class="text-xs text-muted-foreground">Total conversation time</p>
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
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-medium">User Satisfaction</p>
                                <span class="text-sm font-bold">{{ satisfactionRate }}%</span>
                            </div>
                            <Progress :model-value="parseFloat(satisfactionRate)" class="h-2" />
                            <div class="flex items-center justify-between mt-2 text-xs text-muted-foreground">
                                <span>{{ metrics.totalPositiveFeedback }} positive</span>
                                <span>{{ metrics.totalNegativeFeedback }} negative</span>
                            </div>
                        </div>

                        <!-- Engagement Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t">
                            <div>
                                <p class="text-sm text-muted-foreground mb-1">Conversations with Feedback</p>
                                <p class="text-2xl font-bold">{{ metrics.conversationsWithFeedback }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground mb-1">Avg Messages per Chat</p>
                                <p class="text-2xl font-bold">{{ Number(metrics.avgMessagesPerConversation).toFixed(1) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground mb-1">Unique Sessions</p>
                                <p class="text-2xl font-bold">{{ metrics.uniqueSessions.toLocaleString() }}</p>
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Total Tokens Used</p>
                            </div>
                            <p class="text-3xl font-bold">{{ metrics.totalTokens.toLocaleString() }}</p>
                            <p class="text-xs text-muted-foreground">Across all conversations</p>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Avg Tokens per Message</p>
                                <Badge variant="outline">{{ tokenEfficiency }}</Badge>
                            </div>
                            <p class="text-3xl font-bold">{{ Math.round(metrics.avgTokensPerMessage).toLocaleString() }}</p>
                            <p class="text-xs text-muted-foreground">Token efficiency rating</p>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Total Messages</p>
                            </div>
                            <p class="text-3xl font-bold">{{ metrics.totalMessages.toLocaleString() }}</p>
                            <p class="text-xs text-muted-foreground">Assistant messages sent</p>
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
                    <div v-if="!metrics.modelUsage.length" class="text-center py-8 text-muted-foreground">
                        No model usage data available
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="model in metrics.modelUsage" :key="model.name" class="flex items-center gap-4 p-3 rounded-lg border">
                            <div class="h-12 w-12 rounded-full bg-muted/50 border flex items-center justify-center flex-shrink-0">
                                <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-7 w-7" />
                                <Cpu v-else class="h-7 w-7 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="font-medium truncate">{{ model.name }}</p>
                                    <Badge variant="secondary" class="ml-auto">{{ model.percentage }}%</Badge>
                                </div>
                                <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        {{ model.count }} uses
                                    </span>
                                    <span v-if="model.feedback.positive > 0" class="flex items-center gap-1">
                                        <ThumbsUp class="h-3 w-3" />
                                        {{ model.feedback.positive }} positive
                                    </span>
                                </div>
                                <Progress :model-value="parseFloat(model.percentage)" class="h-1.5 mt-2" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>

