<script setup lang="ts">
/**
 * Workspace home: headline numbers (agents, messages, conversations, active
 * sessions) and the daily reply volume of the last week or month. The numbers
 * load once on mount and again after an agent is created.
 */
import { type DashboardStats, fetchDashboardStats } from '@/api/dashboard';
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import OnboardingWelcome from '@/components/onboarding/OnboardingWelcome.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useBilling } from '@/composables/useBilling';
import { useLocaleDate } from '@/composables/useLocaleDate';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { Activity, Bot, MessageSquare, MessagesSquare, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { formatWeekdayShort, formatDayOfMonth, intlLocale } = useLocaleDate();
const { billingEnabled } = useBilling();

// The parts of the dashboard stats this page shows.
type DashboardView = Pick<DashboardStats, 'agents' | 'messages' | 'conversations' | 'active_sessions' | 'weekly_usage' | 'monthly_usage'>;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('dashboard.title'),
        href: '/dashboard',
    },
];

// Zeros until the stats arrive, so the cards can render their skeletons.
const dashboardData = ref<DashboardView>({
    agents: {
        total: 0,
    },
    messages: {
        total: 0,
        available: 0,
        limit: 0,
        cycle: null,
        trend: {
            this_month: 0,
            last_month: 0,
            percent_change: 0,
        },
    },
    conversations: 0,
    active_sessions: 0,
    weekly_usage: [],
    monthly_usage: [],
});

// Activity period tab
const activityPeriod = ref<'week' | 'month'>('week');

const page = usePage<SharedData>();
const loading = ref(true);

// Modal state
const showCreateAgentModal = ref(false);

// Permission checking - only team owners can create agents
const canCreateAgent = computed(() => {
    const currentTeam = page.props.currentTeam;
    if (!currentTeam) return false;

    return currentTeam.is_owner;
});

// A failed load keeps the numbers already shown; the error is only logged.
const fetchDashboardData = async () => {
    try {
        dashboardData.value = await fetchDashboardStats();
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    } finally {
        loading.value = false;
    }
};

// Function to refresh dashboard data after agent creation
const refreshDashboardData = () => {
    fetchDashboardData();
};

const currentActivityData = computed(() => {
    void intlLocale.value;

    const data = activityPeriod.value === 'week' ? dashboardData.value.weekly_usage : dashboardData.value.monthly_usage;

    return data.map((item) => ({
        ...item,
        displayLabel: item.date ? (activityPeriod.value === 'week' ? formatWeekdayShort(item.date) : formatDayOfMonth(item.date)) : item.label,
    }));
});

const maxUsage = computed(() => {
    if (currentActivityData.value.length === 0) return 1;
    return Math.max(...currentActivityData.value.map((item) => item.value), 1);
});

// Share of the cycle's paid quota consumed: used / limit (base + addons).
const messageUsagePercent = computed(() => {
    if (dashboardData.value.messages.limit === 0) return 0;
    return Math.min(100, Math.round((dashboardData.value.messages.total / dashboardData.value.messages.limit) * 100));
});

onMounted(() => {
    fetchDashboardData();
});

const formatNumber = (num: number): string => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
};
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="flex flex-col gap-6 p-6">
                <!-- Header Section -->
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ t('dashboard.title') }}</h1>
                        <p class="text-muted-foreground">{{ t('dashboard.subtitle') }}</p>
                    </div>
                    <Button v-if="canCreateAgent" @click="showCreateAgentModal = true" class="gap-2 self-start">
                        <Plus class="h-4 w-4" />
                        {{ t('dashboard.create_agent') }}
                    </Button>
                </div>

                <!-- Stats Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Agents Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('dashboard.total_agents') }}</CardTitle>
                            <Bot class="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="bg-muted h-8 w-16 animate-pulse rounded"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ dashboardData.agents.total }}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Messages Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">{{
                                billingEnabled ? t('dashboard.messages_this_cycle') : t('dashboard.messages_this_month')
                            }}</CardTitle>
                            <MessageSquare class="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="bg-muted h-8 w-16 animate-pulse rounded"></div>
                            <!-- Without billing there is no credit cycle or limit: show this month's replies. -->
                            <div v-else-if="!billingEnabled">
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.messages.trend.this_month) }}</div>
                            </div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.messages.total) }}</div>
                                <div class="mt-2">
                                    <div class="text-muted-foreground mb-1 flex items-center justify-between text-xs">
                                        <span
                                            >{{ dashboardData.messages.total.toLocaleString() }} /
                                            {{ dashboardData.messages.limit.toLocaleString() }}</span
                                        >
                                        <span>{{ t('dashboard.percent_used', { percent: messageUsagePercent }) }}</span>
                                    </div>
                                    <Progress :model-value="messageUsagePercent" class="h-1" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Conversations Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('dashboard.total_conversations') }}</CardTitle>
                            <MessagesSquare class="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="bg-muted h-8 w-16 animate-pulse rounded"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.conversations) }}</div>
                                <p class="text-muted-foreground text-xs">{{ t('dashboard.all_time_across_agents') }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Active Sessions Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('dashboard.active_sessions') }}</CardTitle>
                            <Activity class="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="bg-muted h-8 w-16 animate-pulse rounded"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.active_sessions) }}</div>
                                <p class="text-muted-foreground text-xs">{{ t('dashboard.visitors_last_24h') }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Activity Chart -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-4">
                        <div>
                            <CardTitle class="text-sm font-medium">{{ t('dashboard.message_activity') }}</CardTitle>
                            <CardDescription>
                                {{ activityPeriod === 'week' ? t('dashboard.volume_week') : t('dashboard.volume_month') }}
                            </CardDescription>
                        </div>
                        <Tabs v-model="activityPeriod" class="w-auto">
                            <TabsList class="h-8">
                                <TabsTrigger value="week" class="px-3 text-xs">{{ t('dashboard.last_week') }}</TabsTrigger>
                                <TabsTrigger value="month" class="px-3 text-xs">{{ t('dashboard.last_month') }}</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="flex h-40 items-end justify-center gap-3 px-2">
                            <div v-for="i in 7" :key="i" class="flex w-full max-w-16 flex-col items-center gap-2">
                                <div class="bg-muted h-20 w-full animate-pulse rounded-lg"></div>
                                <div class="bg-muted h-3 w-6 animate-pulse rounded"></div>
                            </div>
                        </div>
                        <div v-else class="flex h-40 items-end justify-center gap-3 px-2">
                            <div
                                v-for="item in currentActivityData"
                                :key="item.date ?? item.label"
                                class="flex w-full max-w-16 flex-col items-center gap-2"
                            >
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div
                                                class="from-primary to-primary/90 hover:from-primary hover:to-primary/70 w-full cursor-pointer rounded-lg bg-gradient-to-t shadow-sm transition-all duration-300 hover:scale-105 hover:shadow-md"
                                                :style="{ height: `${Math.max((item.value / maxUsage) * 160, 6)}px` }"
                                            ></div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p class="font-medium">{{ t('dashboard.messages_tooltip', item.value) }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <span class="text-muted-foreground text-[10px] font-medium">{{ item.displayLabel }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Create Agent Modal -->
        <CreateAgentModal v-model:open="showCreateAgentModal" @agent-created="refreshDashboardData" />

        <!-- First-run welcome popup + guided tour for new workspace owners -->
        <OnboardingWelcome @start-create="showCreateAgentModal = true" />
    </AppLayout>
</template>
