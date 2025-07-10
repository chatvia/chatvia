<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
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
  Bot,
  Plus,
  MessageSquare,
  Users,
  BarChart3,
  Clock,
  ArrowRight,
  Brain,
  Zap,
  Database
} from 'lucide-vue-next';
import { ScrollArea } from '@/components/ui/scroll-area';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Sample data - in a real app, this would come from your backend
const recentAgents = ref([
  { id: 1, name: 'Customer Support Bot', domain: 'support', messages: 1243, users: 89, lastActive: '2 hours ago' },
  { id: 2, name: 'Product Recommendation Agent', domain: 'products', messages: 856, users: 42, lastActive: '5 hours ago' },
  { id: 3, name: 'Technical FAQ Assistant', domain: 'techhelp', messages: 2105, users: 134, lastActive: '30 minutes ago' },
]);

const totalStats = ref({
  totalAgents: 5,
  totalMessages: 8432,
  totalUsers: 347,
  avgResponseTime: '1.8s'
});

const quickActions = [
  { title: 'Create New Agent', icon: Plus, href: route('agents.create'), color: 'text-emerald-500' },
  { title: 'View All Agents', icon: Bot, href: route('agents.index'), color: 'text-blue-500' },
  { title: 'Knowledge Base', icon: Database, href: '#', color: 'text-amber-500' },
  { title: 'Team Settings', icon: Users, href: '#', color: 'text-purple-500' },
];

const usageTrend = ref([
  { day: 'Mon', value: 45 },
  { day: 'Tue', value: 52 },
  { day: 'Wed', value: 49 },
  { day: 'Thu', value: 63 },
  { day: 'Fri', value: 59 },
  { day: 'Sat', value: 38 },
  { day: 'Sun', value: 42 },
]);

const maxUsage = Math.max(...usageTrend.value.map(day => day.value));

// In a real app, you would fetch this data from your backend
onMounted(() => {
  // Fetch dashboard data
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
                        <Button as="a" :href="route('agents.create')" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create New Agent
                        </Button>
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
                        <Button variant="outline" size="sm" as="a" :href="route('agents.index')" class="gap-1">
                            View All
                            <ArrowRight class="h-3 w-3" />
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="agent in recentAgents" :key="agent.id" class="flex items-center justify-between border-b pb-4 last:border-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10">
                                        <Bot class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <h4 class="font-medium">{{ agent.name }}</h4>
                                        <p class="text-sm text-muted-foreground">{{ agent.domain }}.chatvia.ai</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-sm font-medium">{{ agent.messages }} messages</p>
                                        <p class="text-xs text-muted-foreground">{{ agent.users }} users</p>
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
                        <div class="h-[200px] flex items-end gap-2">
                            <div v-for="day in usageTrend" :key="day.day" class="flex flex-1 flex-col items-center gap-2">
                                <div class="w-full bg-primary rounded-t-sm"
                                     :style="{ height: `${(day.value / maxUsage) * 150}px` }"></div>
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
                        <div class="space-y-4">
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
                                <span class="font-medium">24</span>
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
