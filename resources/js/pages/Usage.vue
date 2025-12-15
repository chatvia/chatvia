<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import {
    MessageSquare,
    FileText,
    Bot,
    Loader2,
    BarChart3,
    TrendingUp,
    Clock,
    Link2,
    Users,
    Layers
} from 'lucide-vue-next';

type UsageData = App.Data.Resources.My.Usage.UsageResourceData;

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
    {
        title: 'Usage',
        href: route('usage'),
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
        <Head title="Usage" />

        <div class="px-4 py-8">
            <div class="space-y-8">
                <!-- Usage Header -->
                <div class="flex flex-col space-y-2">
                    <h1 class="text-2xl font-bold tracking-tight">Usage</h1>
                    <p class="text-muted-foreground">
                        Monitor your current usage across all services and features.
                    </p>
                </div>

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
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
                                    <span class="text-2xl font-bold">{{ usageData.messages.used.toLocaleString() }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.messages.limit.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.messages.used, usageData.messages.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.messages.used, usageData.messages.limit)}%` }"
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

                        <!-- Minutes Usage -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Clock class="h-5 w-5 text-orange-500" />
                                    Minutes
                                </CardTitle>
                                <CardDescription>
                                    Audio transcription minutes used
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.minutes.used.toLocaleString() }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.minutes.limit.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.minutes.used, usageData.minutes.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.minutes.used, usageData.minutes.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.minutes.remaining.toLocaleString() }} remaining</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Links Usage -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Link2 class="h-5 w-5 text-purple-500" />
                                    Links
                                </CardTitle>
                                <CardDescription>
                                    Website links trained from crawler
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.links.used.toLocaleString() }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.links.limit.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.links.used, usageData.links.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.links.used, usageData.links.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.links.remaining.toLocaleString() }} remaining</span>
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
                                    All AI agents
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.agents.used }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.agents.limit }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.agents.used, usageData.agents.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.agents.used, usageData.agents.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.agents.remaining }} remaining</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Teams -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Layers class="h-5 w-5 text-teal-500" />
                                    Teams
                                </CardTitle>
                                <CardDescription>
                                    Teams created
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.teams.used }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.teams.limit }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.teams.used, usageData.teams.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.teams.used, usageData.teams.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.teams.remaining }} remaining</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Members -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Users class="h-5 w-5 text-pink-500" />
                                    Members
                                </CardTitle>
                                <CardDescription>
                                    Team members invited
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ usageData.members.used }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        of {{ usageData.members.limit }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="getUsageColor(getUsagePercentage(usageData.members.used, usageData.members.limit))"
                                        :style="{ width: `${getUsagePercentage(usageData.members.used, usageData.members.limit)}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ usageData.members.remaining }} remaining</span>
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
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-6 text-center">
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-blue-600">{{ getUsagePercentage(usageData.messages.used, usageData.messages.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Messages</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-green-600">{{ getUsagePercentage(usageData.pages.used, usageData.pages.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Pages</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-orange-600">{{ getUsagePercentage(usageData.minutes.used, usageData.minutes.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Minutes</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-purple-600">{{ getUsagePercentage(usageData.links.used, usageData.links.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Links</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-indigo-600">{{ getUsagePercentage(usageData.agents.used, usageData.agents.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Agents</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-teal-600">{{ getUsagePercentage(usageData.teams.used, usageData.teams.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Teams</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-pink-600">{{ getUsagePercentage(usageData.members.used, usageData.members.limit) }}%</div>
                                    <div class="text-xs text-muted-foreground">Members</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-else class="text-center py-12">
                    <p class="text-muted-foreground">Unable to load usage data</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

