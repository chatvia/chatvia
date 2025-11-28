<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import { type BreadcrumbItem } from '@/types';
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
import { Progress } from '@/components/ui/progress';
import {
  Bot,
  Plus,
  MessageSquare,
  ArrowRight,
  MessagesSquare,
  UserPlus,
  FileText,
  Clock,
  Link2
} from 'lucide-vue-next';

// Types for the dashboard data
interface Agent {
  id: number;
  name: string;
  sub_domain: string;
  type: string;
  external_id: string;
  url: string;
  chat_url: string;
  conversations_count: number;
}

interface UsageItem {
  used: number;
  limit: number;
  remaining: number;
}

interface DashboardData {
  agents: {
    total: number;
    widget_count: number;
    knowledge_base_count: number;
    recent: Agent[];
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
  weekly_usage: Array<{
    day: string;
    value: number;
  }>;
  usage: {
    plan: string;
    messages: UsageItem;
    pages: UsageItem;
    minutes: UsageItem;
    links: UsageItem;
  };
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
    recent: []
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
  usage: {
    plan: 'Free',
    messages: { used: 0, limit: 0, remaining: 0 },
    pages: { used: 0, limit: 0, remaining: 0 },
    minutes: { used: 0, limit: 0, remaining: 0 },
    links: { used: 0, limit: 0, remaining: 0 }
  }
});

// Computed usage percentages
const usagePercent = (item: UsageItem) => {
  if (item.limit === 0) return 0;
  return Math.round((item.used / item.limit) * 100);
};

const page = usePage();
const loading = ref(true);

// Modal state
const showCreateAgentModal = ref(false);

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

const maxUsage = computed(() => {
  if (dashboardData.value.weekly_usage.length === 0) return 1;
  return Math.max(...dashboardData.value.weekly_usage.map(day => day.value), 1);
});

const totalWeeklyMessages = computed(() => {
  return dashboardData.value.weekly_usage.reduce((sum, day) => sum + day.value, 0);
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
                    <Button @click="showCreateAgentModal = true" class="gap-2 self-start">
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

                <!-- Recent Agents & Weekly Activity -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <!-- Recent Agents -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <div>
                                <CardTitle class="text-sm font-medium">Recent Agents</CardTitle>
                                <CardDescription>Your most recently created AI agents</CardDescription>
                            </div>
                            <Link :href="route('agents.index')">
                                <Button variant="outline" size="sm" class="h-8 gap-1">
                                    View All
                                    <ArrowRight class="h-3 w-3" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="space-y-2">
                                <div v-for="i in 3" :key="i" class="flex items-center justify-between rounded-md border p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-muted animate-pulse"></div>
                                        <div class="space-y-1">
                                            <div class="h-4 w-28 bg-muted rounded animate-pulse"></div>
                                            <div class="h-3 w-16 bg-muted rounded animate-pulse"></div>
                                        </div>
                                    </div>
                                    <div class="h-4 w-12 bg-muted rounded animate-pulse"></div>
                                </div>
                            </div>
                            <div v-else-if="dashboardData.agents.recent.length === 0" class="text-center py-8">
                                <Bot class="h-10 w-10 text-muted-foreground mx-auto mb-3" />
                                <h3 class="font-medium">No agents yet</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Create your first AI agent to get started</p>
                                <Button @click="showCreateAgentModal = true" variant="outline" size="sm" class="mt-3 gap-1">
                                    <Plus class="h-3 w-3" />
                                    Create Agent
                                </Button>
                            </div>
                            <div v-else class="space-y-2">
                                <Link
                                    v-for="agent in dashboardData.agents.recent"
                                    :key="agent.id"
                                    :href="route('agents.analytics', agent.id)"
                                    class="flex items-center justify-between rounded-md border p-3 hover:bg-accent transition-colors group"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                            <Bot class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium group-hover:underline">{{ agent.name }}</h4>
                                            <p class="text-xs text-muted-foreground">
                                                {{ agent.type === 'widget' ? 'Widget' : 'Knowledge Base' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <span class="text-sm font-medium">{{ agent.conversations_count || 0 }}</span>
                                            <span class="text-xs text-muted-foreground ml-1">chats</span>
                                        </div>
                                        <ArrowRight class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Weekly Activity Chart -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <div>
                                <CardTitle class="text-sm font-medium">Weekly Activity</CardTitle>
                                <CardDescription>Message volume over the past 7 days</CardDescription>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold">{{ totalWeeklyMessages }}</div>
                                <div class="text-xs text-muted-foreground">total messages</div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="flex items-end gap-2 h-32">
                                <div v-for="i in 7" :key="i" class="flex flex-1 flex-col items-center gap-1">
                                    <div class="w-full bg-muted rounded-sm animate-pulse h-16"></div>
                                    <div class="h-3 w-6 bg-muted rounded animate-pulse"></div>
                                </div>
                            </div>
                            <div v-else class="flex items-end gap-2 h-32">
                                <div v-for="(day, index) in dashboardData.weekly_usage" :key="day.day" class="flex flex-1 flex-col items-center gap-1">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <div
                                                    class="w-full rounded-sm bg-primary transition-all hover:bg-primary/80 cursor-pointer"
                                                    :style="{ height: `${Math.max((day.value / maxUsage) * 100, 4)}px` }"
                                                >
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ day.value }} messages</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <span class="text-xs text-muted-foreground">{{ day.day }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Plan Usage -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <div>
                            <CardTitle class="text-sm font-medium">Plan Usage</CardTitle>
                            <CardDescription>
                                <span v-if="loading" class="inline-block h-4 w-32 bg-muted rounded animate-pulse"></span>
                                <span v-else>You are currently on the <span class="font-medium">{{ dashboardData.usage.plan }}</span> plan</span>
                            </CardDescription>
                        </div>
                        <Link :href="route('usage')">
                            <Button variant="outline" size="sm" class="h-8 gap-1">
                                View Details
                                <ArrowRight class="h-3 w-3" />
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div v-for="i in 4" :key="i" class="space-y-2">
                                <div class="h-4 w-20 bg-muted rounded animate-pulse"></div>
                                <div class="h-6 w-16 bg-muted rounded animate-pulse"></div>
                                <div class="h-2 w-full bg-muted rounded animate-pulse"></div>
                            </div>
                        </div>
                        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <!-- Messages -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <MessageSquare class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Messages</span>
                                </div>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-bold">{{ dashboardData.usage.messages.used }}</span>
                                    <span class="text-sm text-muted-foreground">of {{ dashboardData.usage.messages.limit }}</span>
                                </div>
                                <Progress :model-value="usagePercent(dashboardData.usage.messages)" class="h-2" />
                                <p class="text-xs text-muted-foreground">{{ dashboardData.usage.messages.remaining }} remaining</p>
                            </div>

                            <!-- Pages -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Pages</span>
                                </div>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-bold">{{ dashboardData.usage.pages.used }}</span>
                                    <span class="text-sm text-muted-foreground">of {{ dashboardData.usage.pages.limit }}</span>
                                </div>
                                <Progress :model-value="usagePercent(dashboardData.usage.pages)" class="h-2" />
                                <p class="text-xs text-muted-foreground">{{ dashboardData.usage.pages.remaining }} remaining</p>
                            </div>

                            <!-- Minutes -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Minutes</span>
                                </div>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-bold">{{ dashboardData.usage.minutes.used }}</span>
                                    <span class="text-sm text-muted-foreground">of {{ dashboardData.usage.minutes.limit }}</span>
                                </div>
                                <Progress :model-value="usagePercent(dashboardData.usage.minutes)" class="h-2" />
                                <p class="text-xs text-muted-foreground">{{ dashboardData.usage.minutes.remaining }} remaining</p>
                            </div>

                            <!-- Links -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <Link2 class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Links</span>
                                </div>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-bold">{{ dashboardData.usage.links.used }}</span>
                                    <span class="text-sm text-muted-foreground">of {{ dashboardData.usage.links.limit }}</span>
                                </div>
                                <Progress :model-value="usagePercent(dashboardData.usage.links)" class="h-2" />
                                <p class="text-xs text-muted-foreground">{{ dashboardData.usage.links.remaining }} remaining</p>
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
