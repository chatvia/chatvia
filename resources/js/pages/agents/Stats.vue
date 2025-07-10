<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BarChart3, MessageSquare, Users, Clock, ThumbsUp, BarChart, Cpu } from 'lucide-vue-next';

interface Props {
    agent: any
}

defineProps<Props>();

// Mock statistics data
const stats = ref({
    totalMessages: 1543,
    messagesLastWeek: 234,
    uniqueUsers: 87,
    newUsersLastWeek: 12,
    avgResponseTime: '1.2',
    satisfactionRate: 94,
    topQuestions: [
        { text: 'How can I reset my password?', count: 45 },
        { text: 'What are your business hours?', count: 38 },
        { text: 'Do you offer refunds?', count: 27 }
    ],
    modelUsage: [
        { name: 'GPT-4', percentage: 65 },
        { name: 'GPT-3.5 Turbo', percentage: 35 }
    ]
});
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
                                <span class="text-green-500">+{{ stats.messagesLastWeek }}</span> in the last 7 days
                            </div>
                        </div>

                        <div class="rounded-lg border bg-card p-4">
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <Users class="h-4 w-4" />
                                <span class="text-sm font-medium">Unique Users</span>
                            </div>
                            <div class="mt-2 text-2xl font-bold">{{ stats.uniqueUsers }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                <span class="text-green-500">+{{ stats.newUsersLastWeek }}</span> new users this week
                            </div>
                        </div>

                        <div class="rounded-lg border bg-card p-4">
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                <span class="text-sm font-medium">Avg. Response Time</span>
                            </div>
                            <div class="mt-2 text-2xl font-bold">{{ stats.avgResponseTime }}s</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                Based on the last 100 messages
                            </div>
                        </div>

                        <div class="rounded-lg border bg-card p-4">
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <ThumbsUp class="h-4 w-4" />
                                <span class="text-sm font-medium">Satisfaction Rate</span>
                            </div>
                            <div class="mt-2 text-2xl font-bold">{{ stats.satisfactionRate }}%</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                Based on user feedback
                            </div>
                        </div>
                    </div>

                    <!-- Usage Over Time Chart -->
                    <div class="rounded-lg border bg-card p-4">
                        <h3 class="mb-4 text-lg font-medium">Usage Over Time</h3>
                        <div class="h-64 w-full">
                            <!-- Placeholder for chart - would be implemented with a charting library -->
                            <div class="flex h-full w-full items-center justify-center rounded-md border border-dashed border-muted-foreground/50 bg-muted/50">
                                <div class="text-center text-muted-foreground">
                                    <BarChart class="mx-auto h-8 w-8 opacity-50" />
                                    <p class="mt-2">Usage chart will appear here</p>
                                    <p class="text-xs">Data is collected as users interact with your agent</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Questions -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium">Top Questions</h3>
                        <div class="rounded-lg border">
                            <div v-if="!stats.topQuestions || stats.topQuestions.length === 0"
                                 class="p-4 text-center text-muted-foreground">
                                No data available yet
                            </div>
                            <div v-else class="divide-y">
                                <div v-for="(question, index) in stats.topQuestions"
                                     :key="index"
                                     class="flex items-center justify-between p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                            {{ index + 1 }}
                                        </div>
                                        <span>{{ question.text }}</span>
                                    </div>
                                    <Badge variant="secondary">{{ question.count }} times</Badge>
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
                                        <Cpu class="h-5 w-5 text-muted-foreground" />
                                        <span>{{ model.name }}</span>
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
