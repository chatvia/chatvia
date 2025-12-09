<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import {
  Bot,
  Plus,
  MessageSquare,
  ArrowRight,
  MessagesSquare,
  UserPlus
} from 'lucide-vue-next';

// Types for the dashboard data
interface ActivityData {
  label: string;
  value: number;
}

interface DashboardData {
  agents: {
    total: number;
    widget_count: number;
    knowledge_base_count: number;
  };
  messages: {
    total: number;
    available: number;
    trend: {
      this_month: number;
      last_month: number;
      percent_change: number;
    };
  };
  conversations: number;
  leads: number;
  active_sessions: number;
  knowledge_sources: {
    files: number;
    websites: number;
    crawled_pages: number;
    total: number;
  };
  team: {
    members: number;
  };
  weekly_usage: ActivityData[];
  monthly_usage: ActivityData[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Real data from API
const dashboardData = ref<DashboardData>({
  agents: {
    total: 0,
    widget_count: 0,
    knowledge_base_count: 0,
  },
  messages: {
    total: 0,
    available: 0,
    trend: {
      this_month: 0,
      last_month: 0,
      percent_change: 0
    }
  },
  conversations: 0,
  leads: 0,
  active_sessions: 0,
  knowledge_sources: {
    files: 0,
    websites: 0,
    crawled_pages: 0,
    total: 0
  },
  team: {
    members: 0
  },
  weekly_usage: [],
  monthly_usage: []
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

// Fetch dashboard data from API
const fetchDashboardData = async () => {
  try {
    const response = await fetch(route('dashboard.stats'));
    const data: DashboardData = await response.json();
    dashboardData.value = data;
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
  return activityPeriod.value === 'week'
    ? dashboardData.value.weekly_usage
    : dashboardData.value.monthly_usage;
});

const maxUsage = computed(() => {
  if (currentActivityData.value.length === 0) return 1;
  return Math.max(...currentActivityData.value.map(item => item.value), 1);
});

const totalActivityMessages = computed(() => {
  return currentActivityData.value.reduce((sum, item) => sum + item.value, 0);
});

const messageUsagePercent = computed(() => {
  const total = dashboardData.value.messages.total + dashboardData.value.messages.available;
  if (total === 0) return 0;
  return Math.round((dashboardData.value.messages.total / total) * 100);
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
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="flex flex-col gap-6 p-6">
                <!-- Header Section -->
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
                        <p class="text-muted-foreground">Overview of your AI agents and activity</p>
                    </div>
                    <Button v-if="canCreateAgent" @click="showCreateAgentModal = true" class="gap-2 self-start">
                        <Plus class="h-4 w-4" />
                        Create Agent
                    </Button>
                </div>

                <!-- Stats Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Agents Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Agents</CardTitle>
                            <Bot class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="h-8 w-16 bg-muted rounded animate-pulse"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ dashboardData.agents.total }}</div>
                                <p class="text-xs text-muted-foreground">
                                    {{ dashboardData.agents.widget_count }} widgets · {{ dashboardData.agents.knowledge_base_count }} knowledge bases
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Messages Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Messages This Cycle</CardTitle>
                            <MessageSquare class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="h-8 w-16 bg-muted rounded animate-pulse"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.messages.total) }}</div>
                                <div class="mt-2">
                                    <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                                        <span>{{ dashboardData.messages.total }} / {{ dashboardData.messages.total + dashboardData.messages.available }}</span>
                                        <span>{{ messageUsagePercent }}% used</span>
                                    </div>
                                    <Progress :model-value="messageUsagePercent" class="h-1" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Conversations Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Conversations</CardTitle>
                            <MessagesSquare class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="h-8 w-16 bg-muted rounded animate-pulse"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.conversations) }}</div>
                                <p class="text-xs text-muted-foreground">
                                    {{ dashboardData.active_sessions }} active today
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Leads Card -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Leads Captured</CardTitle>
                            <UserPlus class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="h-8 w-16 bg-muted rounded animate-pulse"></div>
                            <div v-else>
                                <div class="text-2xl font-bold">{{ formatNumber(dashboardData.leads) }}</div>
                                <p class="text-xs text-muted-foreground">
                                    From agent conversations
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Activity Chart -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-4">
                        <div>
                            <CardTitle class="text-sm font-medium">Message Activity</CardTitle>
                            <CardDescription>
                                {{ activityPeriod === 'week' ? 'Message volume over the past 7 days' : 'Message volume over the past 30 days' }}
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-4">
                            <Tabs v-model="activityPeriod" class="w-auto">
                                <TabsList class="h-8">
                                    <TabsTrigger value="week" class="text-xs px-3">Last Week</TabsTrigger>
                                    <TabsTrigger value="month" class="text-xs px-3">Last Month</TabsTrigger>
                                </TabsList>
                            </Tabs>
                            <div class="text-right">
                                <div class="text-2xl font-bold">{{ totalActivityMessages }}</div>
                                <div class="text-xs text-muted-foreground">total messages</div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="flex items-end gap-1 h-40">
                            <div v-for="i in 7" :key="i" class="flex flex-1 flex-col items-center gap-1">
                                <div class="w-full bg-muted rounded-sm animate-pulse h-20"></div>
                                <div class="h-3 w-6 bg-muted rounded animate-pulse"></div>
                            </div>
                        </div>
                        <div v-else class="flex items-end gap-1 h-40">
                            <div
                                v-for="item in currentActivityData"
                                :key="item.label"
                                class="flex flex-1 flex-col items-center gap-1"
                            >
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div
                                                class="w-full rounded-sm bg-primary transition-all hover:bg-primary/80 cursor-pointer min-h-[4px]"
                                                :style="{ height: `${Math.max((item.value / maxUsage) * 100, 2)}%` }"
                                            >
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.value }} messages</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <span class="text-[10px] text-muted-foreground">{{ item.label }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </div>

        <!-- Create Agent Modal -->
        <CreateAgentModal
            v-model:open="showCreateAgentModal"
            @agent-created="refreshDashboardData"
        />
    </AppLayout>
</template>
