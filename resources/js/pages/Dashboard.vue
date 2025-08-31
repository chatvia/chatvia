<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
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
import {
  Bot,
  Plus,
  MessageSquare,
  Users,
  BarChart3,
  Clock,
  ArrowRight,
  Brain,
  Zap,
  Database,
  Globe
} from 'lucide-vue-next';
import { ScrollArea } from '@/components/ui/scroll-area';

// Types for the dashboard data
interface Agent {
  id: number;
  name: string;
  sub_domain: string;
  type: string;
  external_id: string;
  url: string;
  chat_url: string;
}

interface DashboardData {
  agents: {
    total: number;
    recent: Agent[];
  };
  messages: number;
  minutes: number;
  performance: {
    avg_response_time: string;
    members: string;
    files: number;
  };
  weekly_usage: Array<{
    day: string;
    value: number;
  }>;
}

interface RecentAgent {
  id: number;
  name: string;
  domain: string;
  type: string;
  externalId: string;
  messages: number;
  users: number;
  lastActive: string;
  url: string;
  chatUrl: string;
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
    recent: []
  },
  messages: 0,
  minutes: 0,
  performance: {
    avg_response_time: '',
    members: '',
    files: 0
  },
  weekly_usage: []
});

const loading = ref(true);

// Computed properties for easier access
const totalStats = ref({
  totalAgents: 0,
  totalMessages: 0,
  totalUsers: 0,
  avgResponseTime: '0s'
});

const recentAgents = ref<RecentAgent[]>([]);
const usageTrend = ref<Array<{day: string; value: number}>>([]);

const quickActions = [
  { title: 'Create New Agent', icon: Plus, href: route('agents.create'), color: 'text-emerald-500' },
  { title: 'View All Agents', icon: Bot, href: route('agents.index'), color: 'text-blue-500' },
  { title: 'Knowledge Base', icon: Database, href: route('knowledge-base.show'), color: 'text-amber-500' },
  { title: 'Members', icon: Users, href: route('members.show'), color: 'text-purple-500' },
];

// Fetch dashboard data from API
const fetchDashboardData = async () => {
  try {
    const response = await fetch(route('dashboard.stats'));
    const data: DashboardData = await response.json();

    dashboardData.value = data;

    // Update computed values
    totalStats.value = {
      totalAgents: data.agents.total,
      totalMessages: data.messages,
      totalUsers: parseInt(data.performance.members) || 0,
      avgResponseTime: data.performance.avg_response_time || '0s'
    };

    recentAgents.value = data.agents.recent.map((agent: Agent) => ({
      id: agent.id,
      name: agent.name,
      domain: agent.sub_domain,
      type: agent.type,
      externalId: agent.external_id,
      messages: 0, // Not provided in API response
      users: 0, // Not provided in API response
      lastActive: 'Recently', // Not provided in API response
      url: agent.url,
      chatUrl: agent.chat_url
    }));

    usageTrend.value = data.weekly_usage;

  } catch (error) {
    console.error('Error fetching dashboard data:', error);
  } finally {
    loading.value = false;
  }
};

const maxUsage = computed(() => {
  if (usageTrend.value.length === 0) return 1;
  return Math.max(...usageTrend.value.map(day => day.value));
});

onMounted(() => {
  fetchDashboardData();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="flex flex-col gap-4 p-4">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Welcome and quick stats -->
            <div class="grid gap-4 md:grid-cols-4">
                <!-- Welcome card -->
                <Card class="md:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-2xl">Welcome to Chatvia</CardTitle>
                        <CardDescription>
                            Create and manage your AI agents with ease
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground">
                            Build powerful conversational AI agents similar to ChatGPT, customize their knowledge base, and deploy them to your users.
                        </p>
                    </CardContent>
                    <CardFooter>
                        <Link :href="route('agents.create')">
                            <Button class="gap-2">
                                <Plus class="h-4 w-4" />
                                Create New Agent
                            </Button>
                        </Link>
                    </CardFooter>
                </Card>

                <!-- Quick stats -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium flex items-center gap-2">
                            <Bot class="h-4 w-4 text-blue-500" />
                            Total Agents
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalStats.totalAgents }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Active and ready to assist
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium flex items-center gap-2">
                            <MessageSquare class="h-4 w-4 text-emerald-500" />
                            Total Messages
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalStats.totalMessages }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Across all your agents
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Middle section -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- Recent agents -->
                <Card class="md:col-span-2">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <div>
                            <CardTitle>Recent Agents</CardTitle>
                            <CardDescription>
                                Your most active AI assistants
                            </CardDescription>
                        </div>
                        <Link :href="route('agents.index')">
                            <Button variant="outline" size="sm" class="gap-1">
                                View All
                                <ArrowRight class="h-3 w-3" />
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="space-y-4">
                            <div v-for="i in 3" :key="i" class="flex items-center justify-between border-b pb-4 last:border-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-muted animate-pulse"></div>
                                    <div>
                                        <div class="h-4 w-32 bg-muted rounded animate-pulse"></div>
                                        <div class="h-3 w-24 bg-muted rounded animate-pulse mt-1"></div>
                                    </div>
                                </div>
                                <div class="h-4 w-16 bg-muted rounded animate-pulse"></div>
                            </div>
                        </div>
                        <div v-else-if="recentAgents.length === 0" class="text-center py-8">
                            <Bot class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                            <p class="text-muted-foreground">No agents created yet</p>
                            <Link :href="route('agents.create')">
                                <Button variant="outline" class="mt-2">
                                    Create Your First Agent
                                </Button>
                            </Link>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="agent in recentAgents" :key="agent.id" class="flex items-center justify-between border-b pb-4 last:border-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10">
                                        <Bot class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <h4 class="font-medium">{{ agent.name }}</h4>
                                        <div class="flex items-center gap-2">
                                            <p v-if="agent.type !== 'widget'" class="text-sm text-muted-foreground">
                                                {{ agent.domain }}.chatvia.ai
                                            </p>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full" 
                                                  :class="agent.type === 'widget' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400' : 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'">
                                                <component :is="agent.type === 'widget' ? Globe : Brain" class="h-3 w-3" />
                                                {{ agent.type === 'widget' ? 'Widget' : 'Knowledge Base' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-sm font-medium">{{ agent.messages }} messages</p>
                                        <p class="text-xs text-muted-foreground">{{ agent.users }} users</p>
                                        <p v-if="agent.type !== 'widget'" class="text-xs text-muted-foreground">
                                            <a :href="agent.chatUrl" target="_blank" class="hover:text-primary">Chat URL</a>
                                        </p>
                                    </div>
                                    <Link :href="route('agents.stats', agent.id)" class="rounded-md p-2 hover:bg-muted">
                                        <ArrowRight class="h-4 w-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Quick actions -->
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                        <CardDescription>
                            Common tasks and shortcuts
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-2">
                            <Link
                                v-for="action in quickActions"
                                :key="action.title"
                                :href="action.href"
                                class="flex items-center gap-3 rounded-lg border p-3 hover:bg-muted transition-colors"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-background" :class="action.color">
                                    <component :is="action.icon" class="h-4 w-4" />
                                </div>
                                <div class="font-medium">{{ action.title }}</div>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Bottom section -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- Usage chart -->
                <Card class="md:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BarChart3 class="h-5 w-5" />
                            Weekly Usage
                        </CardTitle>
                        <CardDescription>
                            Message volume over the past 7 days
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="h-[200px] flex items-end gap-2">
                            <div v-for="i in 7" :key="i" class="flex flex-1 flex-col items-center gap-2">
                                <div class="w-full bg-muted rounded-t-sm animate-pulse" style="height: 60px;"></div>
                                <div class="h-3 w-6 bg-muted rounded animate-pulse"></div>
                            </div>
                        </div>
                        <div v-else class="h-[200px] flex items-end gap-2">
                            <div v-for="day in usageTrend" :key="day.day" class="flex flex-1 flex-col items-center gap-2">
                                <div class="w-full bg-primary rounded-t-sm"
                                     :style="{ height: `${(day.value / maxUsage) * 150}px` }">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <div class="w-full h-full"></div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ day.value }} messages</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <span class="text-xs text-muted-foreground">{{ day.day }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Performance metrics -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Zap class="h-5 w-5" />
                            Performance
                        </CardTitle>
                        <CardDescription>
                            Key metrics and response times
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="space-y-4">
                            <div v-for="i in 3" :key="i" class="flex items-center justify-between">
                                <div class="h-4 w-24 bg-muted rounded animate-pulse"></div>
                                <div class="h-4 w-12 bg-muted rounded animate-pulse"></div>
                            </div>
                        </div>
                        <div v-else class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">Avg. Response Time</span>
                                </div>
                                <span class="font-medium">{{ totalStats.avgResponseTime }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">Total Users</span>
                                </div>
                                <span class="font-medium">{{ totalStats.totalUsers }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Brain class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">Knowledge Files</span>
                                </div>
                                <span class="font-medium">{{ dashboardData.performance.files }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
        </div>
        </div>
    </AppLayout>
</template>

