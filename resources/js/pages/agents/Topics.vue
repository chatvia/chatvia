<script lang="ts" setup>
import LockedFeature from '@/components/LockedFeature.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import AgentLayout from '@/layouts/AgentLayout.vue';
import axios from 'axios';
import {
    ArcElement,
    CategoryScale,
    Chart as ChartJS,
    Tooltip as ChartTooltip,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    type ChartOptions,
} from 'chart.js';
import { ChevronLeft, ChevronRight, Info, LoaderCircle, Maximize2, MessageSquare, Plus, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Line, Pie } from 'vue-chartjs';
import { toast } from 'vue-sonner';

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, ChartTooltip, Legend, Filler);

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

const props = defineProps<Props>();

const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('topics'));

const loading = ref(true);
const topicsData = ref<App.Data.Agent.Topics.TopicsResourceData | null>(null);
const error = ref<string | null>(null);
const activeView = ref<'graph' | 'pie'>('graph');

// Topics management dialog
const showTopicsDialog = ref(false);
const showInfoDialog = ref(false);
const allTopics = ref<any[]>([]);
const agentTopics = ref<any[]>([]);
const loadingTopics = ref(false);
const searchQuery = ref('');
const newTopicName = ref('');
const newTopicDescription = ref('');
const showAddForm = ref(false);
const isAddingTopic = ref(false);

// Pagination
const currentPage = ref(1);
const itemsPerPage = 10;

// Predefined color palette for topics
const colors = [
    'rgb(139, 92, 246)', // Purple
    'rgb(59, 130, 246)', // Blue
    'rgb(16, 185, 129)', // Green
    'rgb(236, 72, 153)', // Pink
    'rgb(251, 146, 60)', // Orange
    'rgb(234, 179, 8)', // Yellow
    'rgb(239, 68, 68)', // Red
    'rgb(107, 114, 128)', // Gray
];

const fetchTopicsData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('agents.topics.data', props.agent.external_id));
        topicsData.value = response.data;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load topics data';
        console.error('Error fetching topics data:', err);
    } finally {
        loading.value = false;
    }
};

// Format date for display (e.g., "Dec 3")
const formatDate = (dateStr: string): string => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Line chart data
const lineChartData = computed(() => {
    if (!topicsData.value) return null;

    const datasets = topicsData.value.topics_by_date.map((topic, index) => {
        const color = colors[index % colors.length];

        return {
            label: topic.topic_name,
            data: topic.counts.map((d) => d.count),
            borderColor: color,
            backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
        };
    });

    const firstTopic = topicsData.value.topics_by_date[0];
    const labels = firstTopic ? firstTopic.counts.map((d) => formatDate(d.date)) : [];

    return {
        labels,
        datasets,
    };
});

const lineChartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                    size: 12,
                },
                boxWidth: 8,
                boxHeight: 8,
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14,
            },
            bodyFont: {
                size: 13,
            },
            displayColors: true,
            callbacks: {
                label: function (context) {
                    return `${context.dataset.label}: ${context.parsed.y}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
                precision: 0,
            },
        },
    },
};

// Pie chart data
const pieChartData = computed(() => {
    if (!topicsData.value) return null;

    const totals = topicsData.value.total_topics;

    return {
        labels: totals.map((t) => t.name),
        datasets: [
            {
                data: totals.map((t) => t.count),
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2,
            },
        ],
    };
});

const pieChartOptions: ChartOptions<'pie'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                    size: 12,
                },
                boxWidth: 8,
                boxHeight: 8,
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14,
            },
            bodyFont: {
                size: 13,
            },
            callbacks: {
                label: function (context) {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = topicsData.value?.total_topics.reduce((sum, t) => sum + t.count, 0) ?? 0;
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                },
            },
        },
    },
};

// Fetch all topics and agent topics
const fetchAllTopics = async () => {
    loadingTopics.value = true;
    try {
        const response = await axios.get(route('agents.topics.index', props.agent.external_id));
        agentTopics.value = response.data;

        // Get all topics from the database
        const allTopicsResponse = await axios.get(route('agents.topics.index', props.agent.external_id), {
            params: { all: true },
        });
        allTopics.value = allTopicsResponse.data;
    } catch (err: any) {
        console.error('Error fetching topics:', err);
        toast.error('Failed to load topics');
    } finally {
        loadingTopics.value = false;
    }
};

// Filtered topics based on search
const filteredTopics = computed(() => {
    if (!searchQuery.value) return allTopics.value;

    const query = searchQuery.value.toLowerCase();
    return allTopics.value.filter((topic) => topic.topic.toLowerCase().includes(query) || topic.description.toLowerCase().includes(query));
});

// Paginated topics
const paginatedTopics = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredTopics.value.slice(start, end);
});

// Total pages
const totalPages = computed(() => {
    return Math.ceil(filteredTopics.value.length / itemsPerPage);
});

// Watch search query to reset page
watch(searchQuery, () => {
    currentPage.value = 1;
});

// Check if a topic is associated with the agent
const isTopicAssociated = (topicId: number) => {
    return agentTopics.value.some((t) => t.id === topicId);
};

// Add topic to agent
const addTopicToAgent = async (topicId: number) => {
    try {
        await axios.post(route('agents.topics.store', props.agent.external_id), {
            topic_id: topicId,
        });

        // Refresh topics
        await fetchAllTopics();
        await fetchTopicsData();

        toast.success('Topic added successfully');
    } catch (err: any) {
        console.error('Error adding topic:', err);
        toast.error(err.response?.data?.message || 'Failed to add topic');
    }
};

// Remove topic from agent
const removeTopicFromAgent = async (topicId: number) => {
    try {
        await axios.delete(route('agents.topics.destroy', { agent: props.agent.external_id, topic: topicId }));

        // Refresh topics
        await fetchAllTopics();
        await fetchTopicsData();

        toast.success('Topic removed successfully');
    } catch (err: any) {
        console.error('Error removing topic:', err);
        toast.error(err.response?.data?.message || 'Failed to remove topic');
    }
};

// Create new topic
const createNewTopic = async () => {
    if (!newTopicName.value.trim() || !newTopicDescription.value.trim()) {
        toast.error('Please fill in all fields');
        return;
    }

    isAddingTopic.value = true;
    try {
        await axios.post(route('agents.topics.store', props.agent.external_id), {
            topic: newTopicName.value.trim(),
            description: newTopicDescription.value.trim(),
        });

        // Reset form
        newTopicName.value = '';
        newTopicDescription.value = '';
        showAddForm.value = false;

        // Refresh topics
        await fetchAllTopics();
        await fetchTopicsData();

        toast.success('Topic created and added successfully');
    } catch (err: any) {
        console.error('Error creating topic:', err);
        toast.error(err.response?.data?.message || 'Failed to create topic');
    } finally {
        isAddingTopic.value = false;
    }
};

// Open topics dialog
const openTopicsDialog = () => {
    showTopicsDialog.value = true;
    fetchAllTopics();
};

// Watch dialog close to reset search
watch(showTopicsDialog, (newValue) => {
    if (!newValue) {
        searchQuery.value = '';
        showAddForm.value = false;
        newTopicName.value = '';
        newTopicDescription.value = '';
        currentPage.value = 1;
    }
});

onMounted(() => {
    if (hasAccess.value) {
        fetchTopicsData();
    }
});
</script>

<template>
    <AgentLayout :agent="agent" title="Topics" description="Analyze conversation topics and trends">
        <template #header-icon>
            <MessageSquare class="h-5 w-5" />
        </template>

        <LockedFeature
            v-if="!hasAccess"
            description="Upgrade your plan to access topic analysis and automatically detect and categorize conversation topics."
        />

        <template v-else>
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="flex items-center justify-center py-12">
                <Card class="max-w-md">
                    <CardContent class="pt-6">
                        <div class="space-y-2 text-center">
                            <p class="text-destructive font-medium">{{ error }}</p>
                            <button @click="fetchTopicsData" class="text-muted-foreground hover:text-foreground text-sm underline">Try again</button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Content -->
            <div v-else-if="topicsData">
                <!-- Topics Chart with Sidebar -->
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <CardTitle>Topics</CardTitle>
                                <button @click="showInfoDialog = true" class="text-muted-foreground hover:text-foreground transition-colors">
                                    <Info class="h-4 w-4 cursor-pointer" />
                                </button>
                            </div>
                            <Tabs v-model="activeView" class="w-auto">
                                <TabsList>
                                    <TabsTrigger value="graph" @click="activeView = 'graph'"> Graph </TabsTrigger>
                                    <TabsTrigger value="pie" @click="activeView = 'pie'"> Pie Chart </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col gap-6 lg:flex-row">
                            <!-- Chart Area -->
                            <div class="flex-1">
                                <!-- Line Chart -->
                                <div v-if="activeView === 'graph'" class="h-96">
                                    <Line v-if="lineChartData" :data="lineChartData" :options="lineChartOptions" />
                                </div>

                                <!-- Pie Chart -->
                                <div v-else class="flex h-96 items-center justify-center">
                                    <div class="w-full max-w-md">
                                        <Pie v-if="pieChartData" :data="pieChartData" :options="pieChartOptions" />
                                    </div>
                                </div>
                            </div>

                            <!-- Topics Sidebar -->
                            <div class="w-full border-t pt-6 lg:w-80 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
                                <div class="flex h-96 flex-col space-y-4">
                                    <!-- Total Topics -->
                                    <div>
                                        <div class="text-3xl font-bold">{{ topicsData.total_count }}</div>
                                        <div class="text-muted-foreground text-sm">Total topics</div>
                                    </div>

                                    <!-- Topics List -->
                                    <div
                                        v-if="topicsData.total_topics.length === 0"
                                        class="text-muted-foreground flex flex-1 items-center justify-center"
                                    >
                                        <p>No topics data available</p>
                                    </div>
                                    <div v-else class="flex-1 space-y-3 overflow-y-auto">
                                        <div
                                            v-for="(topic, index) in topicsData.total_topics"
                                            :key="topic.id"
                                            class="flex items-center justify-between"
                                        >
                                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                                <div
                                                    class="h-3 w-3 flex-shrink-0 rounded-full"
                                                    :style="{ backgroundColor: colors[index % colors.length] }"
                                                ></div>
                                                <span class="truncate text-sm font-medium">{{ topic.name }}</span>
                                            </div>
                                            <span class="ml-2 text-sm font-semibold">{{ topic.count }}</span>
                                        </div>
                                    </div>

                                    <!-- View All Button at Bottom -->
                                    <div class="border-t pt-2">
                                        <Button variant="outline" size="sm" class="w-full" @click="openTopicsDialog">
                                            <span class="flex items-center justify-center gap-2">
                                                View All
                                                <Maximize2 class="h-4 w-4" />
                                            </span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Topics Management Dialog -->
            <Dialog v-model:open="showTopicsDialog">
                <DialogContent class="flex max-h-[80vh] max-w-2xl flex-col">
                    <DialogHeader>
                        <DialogTitle>Manage Topics</DialogTitle>
                        <DialogDescription> Add or remove topics for this agent. Topics help categorize conversations. </DialogDescription>
                    </DialogHeader>

                    <div class="flex flex-1 flex-col space-y-4 overflow-hidden">
                        <!-- Search Bar -->
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input v-model="searchQuery" placeholder="Search topics..." class="pl-10" />
                        </div>

                        <!-- Add New Topic Button/Form -->
                        <div class="space-y-3">
                            <Button v-if="!showAddForm" @click="showAddForm = true" variant="outline" size="sm" class="w-full">
                                <Plus class="mr-2 h-4 w-4" />
                                Add New Topic
                            </Button>

                            <!-- Add Topic Form -->
                            <div v-else class="space-y-3 rounded-lg border p-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Topic Name</label>
                                    <Input v-model="newTopicName" placeholder="e.g., Technical Support" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Description</label>
                                    <Input v-model="newTopicDescription" placeholder="e.g., Technical issues and troubleshooting" />
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        @click="createNewTopic"
                                        :disabled="isAddingTopic || !newTopicName.trim() || !newTopicDescription.trim()"
                                        size="sm"
                                        class="flex-1"
                                    >
                                        <LoaderCircle v-if="isAddingTopic" class="mr-2 h-4 w-4 animate-spin" />
                                        Create & Add
                                    </Button>
                                    <Button @click="showAddForm = false" variant="outline" size="sm"> Cancel </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Topics List -->
                        <div class="flex-1 overflow-y-auto rounded-lg border">
                            <div v-if="loadingTopics" class="flex items-center justify-center py-12">
                                <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                            </div>

                            <div v-else-if="filteredTopics.length === 0" class="text-muted-foreground py-12 text-center">
                                <p>No topics found</p>
                            </div>

                            <div v-else class="divide-y">
                                <div v-for="topic in paginatedTopics" :key="topic.id" class="hover:bg-muted/50 p-4 transition-colors">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-medium">{{ topic.topic }}</h4>
                                                <Badge v-if="isTopicAssociated(topic.id)" variant="secondary" class="text-xs"> Active </Badge>
                                            </div>
                                            <p class="text-muted-foreground mt-1 text-sm">{{ topic.description }}</p>
                                        </div>

                                        <!-- Add/Remove Button -->
                                        <Button
                                            v-if="isTopicAssociated(topic.id)"
                                            @click="removeTopicFromAgent(topic.id)"
                                            variant="outline"
                                            size="sm"
                                            class="flex-shrink-0"
                                        >
                                            <X class="mr-1 h-4 w-4" />
                                            Remove
                                        </Button>
                                        <Button v-else @click="addTopicToAgent(topic.id)" variant="default" size="sm" class="flex-shrink-0">
                                            <Plus class="mr-1 h-4 w-4" />
                                            Add
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="flex items-center justify-between border-t px-4 py-3">
                            <div class="text-muted-foreground text-sm">
                                Page {{ currentPage }} of {{ totalPages }} ({{ filteredTopics.length }} topics)
                            </div>
                            <div class="flex gap-2">
                                <Button @click="currentPage--" :disabled="currentPage === 1" variant="outline" size="sm">
                                    <ChevronLeft class="h-4 w-4" />
                                </Button>
                                <Button @click="currentPage++" :disabled="currentPage === totalPages" variant="outline" size="sm">
                                    <ChevronRight class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button @click="showTopicsDialog = false" variant="outline"> Close </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Info Dialog - What is Topics? -->
            <Dialog v-model:open="showInfoDialog">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>What are Topics?</DialogTitle>
                        <DialogDescription> Learn how topics help organize and analyze your conversations </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 text-sm">
                        <div>
                            <h3 class="mb-2 font-semibold">📊 Overview</h3>
                            <p class="text-muted-foreground">
                                Topics are categories that help you understand what your users are talking about. Each conversation is automatically
                                assigned one topic based on its content.
                            </p>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">🎯 How it works</h3>
                            <ul class="text-muted-foreground list-inside list-disc space-y-2">
                                <li>Conversations are analyzed and categorized into topics</li>
                                <li>You can customize which topics are available for your agent</li>
                                <li>Topics help you identify trends and common issues</li>
                                <li>Each conversation belongs to exactly one topic</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">💡 Examples</h3>
                            <div class="text-muted-foreground space-y-2">
                                <div class="flex items-start gap-2">
                                    <span class="text-primary">•</span>
                                    <span><strong>Technical Support:</strong> Users asking for help with bugs or errors</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-primary">•</span>
                                    <span><strong>Billing & Payments:</strong> Questions about invoices, refunds, subscriptions</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-primary">•</span>
                                    <span><strong>Product Information:</strong> Inquiries about features and specifications</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">✨ Benefits</h3>
                            <ul class="text-muted-foreground list-inside list-disc space-y-2">
                                <li>Track which topics are most popular</li>
                                <li>Identify areas that need more attention</li>
                                <li>Measure conversation volume by category</li>
                                <li>Improve your agent's knowledge base</li>
                            </ul>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button @click="showInfoDialog = false"> Got it </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </template>
    </AgentLayout>
</template>
