<script lang="ts" setup>
import { computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';
import { 
    MessageSquare,
    FileText,
    Bot,
    Database,
    Loader2,
    BarChart3,
    TrendingUp
} from 'lucide-vue-next';

interface UsageData {
    subscription: {
        plan: string;
    };
    agents: {
        total: {
            limit: number;
            created: number;
            remaining: number;
        };
    };
    pages: {
        limit: number;
        used: number;
        remaining: number;
    };
    messages: {
        sent: number;
        limit: number;
        remaining: number;
    };
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Usage statistics',
        href: '/settings/usage',
    },
];

// Usage data
const usageData = ref<UsageData | null>(null);
const isLoadingUsage = ref(true);

const fetchUsageData = async () => {
    try {
        isLoadingUsage.value = true;
        const response = await fetch(route('usage'), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            usageData.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to fetch usage data:', error);
    } finally {
        isLoadingUsage.value = false;
    }
};

const getUsagePercentage = (used: number, limit: number) => {
    if (limit === 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
};

const getUsageColor = (percentage: number) => {
    if (percentage >= 90) return 'bg-red-500';
    if (percentage >= 75) return 'bg-orange-500';
    if (percentage >= 50) return 'bg-yellow-500';
    return 'bg-green-500';
};



onMounted(() => {
    fetchUsageData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Usage statistics" />
        
        <SettingsLayout>
            <div class="space-y-8">
                <!-- Usage Header -->
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="rounded-full bg-primary/10 p-2">
                            <BarChart3 class="size-6 text-primary" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">Usage Statistics</h1>
                    </div>
                    <p class="text-muted-foreground">
                        Monitor your current usage across all services and features to stay within your plan limits.
                    </p>
                </div>

                <Separator />

                <div v-if="isLoadingUsage" class="flex items-center justify-center py-12">
                    <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                </div>

                <div v-else-if="usageData" class="space-y-8">
                    <!-- Current Plan -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <TrendingUp class="h-5 w-5 text-primary" />
                                Current Plan
                            </CardTitle>
                            <CardDescription>
                                You are currently on the {{ usageData.subscription.plan }} plan
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <!-- Usage Details -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Messages Usage -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <MessageSquare class="h-5 w-5 text-blue-500" />
                                    Messages
                                </CardTitle>
                                <CardDescription>
                                    AI chat messages sent this billing period
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.messages.sent.toLocaleString() }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.messages.limit.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.messages.sent, usageData.messages.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.messages.sent, usageData.messages.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.messages.remaining.toLocaleString() }} remaining</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Pages Usage -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <FileText class="h-5 w-5 text-green-500" />
                                    Pages
                                </CardTitle>
                                <CardDescription>
                                    Document pages processed for knowledge base
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.pages.used.toLocaleString() }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.pages.limit.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.pages.used, usageData.pages.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.pages.used, usageData.pages.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.pages.remaining.toLocaleString() }} remaining</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Total Agents -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Bot class="h-5 w-5 text-blue-500" />
                                    Total Agents
                                </CardTitle>
                                <CardDescription>
                                    All AI agents (Widget & ChatGPT-style)
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.agents.total.created }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.agents.total.limit }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.agents.total.created, usageData.agents.total.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.agents.total.created, usageData.agents.total.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.agents.total.remaining }} remaining</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Usage Summary -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <TrendingUp class="h-5 w-5 text-primary" />
                                Usage Summary
                            </CardTitle>
                            <CardDescription>
                                Overview of your current resource utilization across all services
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-center">
                                    <div class="space-y-1">
                                        <div class="text-2xl font-bold text-blue-600">{{ getUsagePercentage(usageData.messages.sent, usageData.messages.limit) }}%</div>
                                        <div class="text-xs text-muted-foreground">Messages Used</div>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="text-2xl font-bold text-green-600">{{ getUsagePercentage(usageData.pages.used, usageData.pages.limit) }}%</div>
                                        <div class="text-xs text-muted-foreground">Pages Used</div>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="text-2xl font-bold text-purple-600">{{ getUsagePercentage(usageData.agents.total.created, usageData.agents.total.limit) }}%</div>
                                        <div class="text-xs text-muted-foreground">Total Agents</div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-else class="text-center py-12">
                    <p class="text-muted-foreground">Unable to load usage data</p>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template> 