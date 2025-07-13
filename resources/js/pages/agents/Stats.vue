<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BarChart3, MessageSquare, Users, Clock, ThumbsUp, BarChart, Cpu } from 'lucide-vue-next';
import { useTimeAgo } from '@/composables/useTimeAgo';

interface Props {
    agent: any
    analytics: any
}

const props = defineProps<Props>();

// Use time ago composable
const { formatTimeAgo } = useTimeAgo();

// Process chart data for last 14 days
const chartData = computed(() => {
    if (!props.analytics?.dailyMessages) return [];
    
    return props.analytics.dailyMessages.map((item: any) => ({
        date: item.date,
        dayMonth: new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        fullDate: new Date(item.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        messages: item.messages || 0,
    }));
});

const maxMessages = computed(() => {
    if (!chartData.value.length) return 100;
    return Math.max(...chartData.value.map((day: any) => day.messages));
});

// Use real analytics data
const stats = computed(() => ({
    totalMessages: props.analytics?.totalMessages || 0,
    messagesLastWeek: props.analytics?.messagesLastWeek || 0,
    uniqueUsers: props.analytics?.uniqueUsers || 0,
    newUsersLastWeek: props.analytics?.newUsersLastWeek || 0,
    avgResponseTime: props.analytics?.avgResponseTime || '0s',
    satisfactionRate: props.analytics?.satisfactionRate || 0,
    topKnowledge: props.analytics?.topKnowledge || [],
    modelUsage: props.analytics?.modelUsage || []
}));
</script>

<template>
    <AgentLayout :agent="agent" title="Agent Statistics">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BarChart3 class="h-5 w-5" />
                    Agent Statistics
                </CardTitle>
                <CardDescription>
                    Performance metrics and usage statistics for your agent
                </CardDescription>
                <div class="flex items-center gap-2 mt-2 text-xs text-muted-foreground">
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 bg-amber-500 rounded-full"></div>
                        <span>Statistics are 10 minutes delayed</span>
                    </div>
                    <span>•</span>
                    <span>Last updated: {{ formatTimeAgo(props.analytics?.lastUpdated) }}</span>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-6">
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                        <div class="rounded-lg border bg-card p-4">
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
                            <div class="mt-2 text-2xl font-bold">{{ stats.avgResponseTime }}s</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                Time taken to respond to a message
                            </div>
                        </div>

                        <div class="rounded-lg border bg-card p-4">
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <ThumbsUp class="h-4 w-4" />
                                <span class="text-sm font-medium">Satisfaction Rate</span>
                            </div>
                            <div class="mt-2 text-2xl font-bold">{{ stats.satisfactionRate }}%</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                Based on your members feedback
                            </div>
                        </div>
                    </div>

                    <!-- Usage Over Time Chart -->
                    <div class="rounded-lg border bg-card p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium">Messages Over Time - Last 14 Days</h3>
                            <a href="#" class="text-sm text-primary hover:underline">View More</a>
                        </div>
                        <div class="h-64 w-full">
                            <div v-if="!chartData.length" class="flex h-full w-full items-center justify-center rounded-md border border-dashed border-muted-foreground/50 bg-muted/50">
                                <div class="text-center text-muted-foreground">
                                    <BarChart class="mx-auto h-8 w-8 opacity-50" />
                                    <p class="mt-2">No data available</p>
                                    <p class="text-xs">Data will appear as users interact with your agent</p>
                                </div>
                            </div>
                            <div v-else class="h-full flex items-end gap-1">
                                <div v-for="day in chartData" :key="day.date" class="flex flex-1 flex-col items-center gap-2 group">
                                    <div class="w-full bg-primary rounded-t-sm cursor-pointer relative"
                                         :style="{ height: `${(day.messages / maxMessages) * 200}px` }">
                                        <!-- Hover tooltip -->
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                            {{ day.fullDate }}: {{ day.messages }} messages
                                        </div>
                                    </div>
                                </div>
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
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                            {{ index + 1 }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ knowledge.name }}</span>
                                            <span class="text-xs text-muted-foreground">{{ knowledge.type }}</span>
                                        </div>
                                    </div>
                                    <Badge variant="secondary">{{ knowledge.accessed }} times</Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Model Usage -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium">Model Usage</h3>
                        <div class="rounded-lg border">
                            <div v-if="!stats.modelUsage || stats.modelUsage.length === 0"
                                 class="p-4 text-center text-muted-foreground">
                                No model usage data available yet
                            </div>
                            <div v-else class="divide-y">
                                <div v-for="(model, index) in stats.modelUsage"
                                     :key="index"
                                     class="flex items-center justify-between p-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Model Icon in Circle -->
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted/50 border">
                                            <img 
                                                v-if="model.icon" 
                                                :src="`/images/llms/${model.icon}`" 
                                                :alt="model.name"
                                                class="h-6 w-6 object-contain"
                                            />
                                            <Cpu v-else class="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ model.name }}</span>
                                            <span v-if="model.description" class="text-xs text-muted-foreground">{{ model.description }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-32 bg-muted rounded-full h-2">
                                            <div class="bg-primary h-2 rounded-full transition-all duration-300"
                                                 :style="{ width: `${model.percentage}%` }"></div>
                                        </div>
                                        <span class="text-sm text-muted-foreground">{{ model.percentage }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </AgentLayout>
</template>
