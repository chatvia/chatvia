<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Line, Pie } from 'vue-chartjs';
import { 
    Chart as ChartJS, 
    CategoryScale, 
    LinearScale, 
    PointElement, 
    LineElement,
    ArcElement,
    Title, 
    Tooltip as ChartTooltip, 
    Legend,
    Filler,
    type ChartOptions
} from 'chart.js';
import { 
    MessageSquare, LoaderCircle, Info, Maximize2, Search, Plus, X, Check, ChevronLeft, ChevronRight
} from 'lucide-vue-next';
import axios from 'axios';
import { toast } from 'vue-sonner';

// Register Chart.js components
ChartJS.register(
    CategoryScale, 
    LinearScale, 
    PointElement, 
    LineElement, 
    ArcElement,
    Title, 
    ChartTooltip, 
    Legend,
    Filler
);

interface Props {
    agent: any
}

const props = defineProps<Props>();

const loading = ref(true);
const topicsData = ref<any>(null);
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
    'rgb(139, 92, 246)',  // Purple
    'rgb(59, 130, 246)',  // Blue
    'rgb(16, 185, 129)',  // Green
    'rgb(236, 72, 153)',  // Pink
    'rgb(251, 146, 60)',  // Orange
    'rgb(234, 179, 8)',   // Yellow
    'rgb(239, 68, 68)',   // Red
    'rgb(107, 114, 128)', // Gray
];

const fetchTopicsData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('agents.topics.data', props.agent.id));
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

    const datasets = [];
    let colorIndex = 0;

    for (const [topicName, data] of Object.entries(topicsData.value.topicsByDate)) {
        const color = colors[colorIndex % colors.length];
        
        datasets.push({
            label: topicName,
            data: (data as any[]).map((d: any) => d.count),
            borderColor: color,
            backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
        });

        colorIndex++;
    }

    const dates = Object.values(topicsData.value.topicsByDate)[0] as any[];
    const labels = dates ? dates.map((d: any) => formatDate(d.date)) : [];

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
                label: function(context) {
                    return `${context.dataset.label}: ${context.parsed.y}`;
                }
            }
        }
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
                }
            }
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
            }
        }
    }
};

// Pie chart data
const pieChartData = computed(() => {
    if (!topicsData.value) return null;

    const totals = topicsData.value.totalTopics;

    return {
        labels: totals.map((t: any) => t.name),
        datasets: [{
            data: totals.map((t: any) => t.count),
            backgroundColor: colors,
            borderColor: '#fff',
            borderWidth: 2,
        }]
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
            }
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
                label: function(context) {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = topicsData.value.totalTopics.reduce((sum: number, t: any) => sum + t.count, 0);
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                }
            }
        }
    }
};

// Fetch all topics and agent topics
const fetchAllTopics = async () => {
    loadingTopics.value = true;
    try {
        const response = await axios.get(route('agents.topics.list', props.agent.id));
        agentTopics.value = response.data;
        
        // Get all topics from the database
        const allTopicsResponse = await axios.get(route('agents.topics.list', props.agent.id), {
            params: { all: true }
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
    return allTopics.value.filter(topic => 
        topic.topic.toLowerCase().includes(query) || 
        topic.description.toLowerCase().includes(query)
    );
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
    return agentTopics.value.some(t => t.id === topicId);
};

// Add topic to agent
const addTopicToAgent = async (topicId: number) => {
    try {
        await axios.post(route('agents.topics.store', props.agent.id), {
            topic_id: topicId
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
        await axios.delete(route('agents.topics.delete', { agent: props.agent.id, topic: topicId }));
        
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
        await axios.post(route('agents.topics.store', props.agent.id), {
            topic: newTopicName.value.trim(),
            description: newTopicDescription.value.trim()
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
    fetchTopicsData();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Topics" description="Analyze conversation topics and trends">
        <template #header-icon>
            <MessageSquare class="h-5 w-5" />
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-12">
            <Card class="max-w-md">
                <CardContent class="pt-6">
                    <div class="text-center space-y-2">
                        <p class="text-destructive font-medium">{{ error }}</p>
                        <button
                            @click="fetchTopicsData"
                            class="text-sm text-muted-foreground hover:text-foreground underline"
                        >
                            Try again
                        </button>
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
                            <button
                                @click="showInfoDialog = true"
                                class="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <Info class="h-4 w-4 cursor-pointer" />
                            </button>
                        </div>
                        <Tabs v-model="activeView" class="w-auto">
                            <TabsList>
                                <TabsTrigger value="graph" @click="activeView = 'graph'">
                                    Graph
                                </TabsTrigger>
                                <TabsTrigger value="pie" @click="activeView = 'pie'">
                                    Pie Chart
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col lg:flex-row gap-6">
                        <!-- Chart Area -->
                        <div class="flex-1">
                            <!-- Line Chart -->
                            <div v-if="activeView === 'graph'" class="h-96">
                                <Line
                                    v-if="lineChartData"
                                    :data="lineChartData"
                                    :options="lineChartOptions"
                                />
                            </div>

                            <!-- Pie Chart -->
                            <div v-else class="h-96 flex items-center justify-center">
                                <div class="w-full max-w-md">
                                    <Pie
                                        v-if="pieChartData"
                                        :data="pieChartData"
                                        :options="pieChartOptions"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Topics Sidebar -->
                        <div class="w-full lg:w-80 border-t lg:border-t-0 lg:border-l pt-6 lg:pt-0 lg:pl-6">
                            <div class="flex flex-col h-96 space-y-4">
                                <!-- Total Topics -->
                                <div>
                                    <div class="text-3xl font-bold">{{ topicsData.totalCount }}</div>
                                    <div class="text-sm text-muted-foreground">Total topics</div>
                                </div>

                                <!-- Topics List -->
                                <div v-if="topicsData.totalTopics.length === 0" class="flex-1 flex items-center justify-center text-muted-foreground">
                                    <p>No topics data available</p>
                                </div>
                                <div v-else class="flex-1 overflow-y-auto space-y-3">
                                    <div
                                        v-for="(topic, index) in topicsData.totalTopics"
                                        :key="topic.id"
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <div
                                                class="h-3 w-3 rounded-full flex-shrink-0"
                                                :style="{ backgroundColor: colors[index % colors.length] }"
                                            ></div>
                                            <span class="text-sm font-medium truncate">{{ topic.name }}</span>
                                        </div>
                                        <span class="text-sm font-semibold ml-2">{{ topic.count }}</span>
                                    </div>
                                </div>

                                <!-- View All Button at Bottom -->
                                <div class="pt-2 border-t">
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
            <DialogContent class="max-w-2xl max-h-[80vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Manage Topics</DialogTitle>
                    <DialogDescription>
                        Add or remove topics for this agent. Topics help categorize conversations.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex-1 overflow-hidden flex flex-col space-y-4">
                    <!-- Search Bar -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search topics..."
                            class="pl-10"
                        />
                    </div>

                    <!-- Add New Topic Button/Form -->
                    <div class="space-y-3">
                        <Button
                            v-if="!showAddForm"
                            @click="showAddForm = true"
                            variant="outline"
                            size="sm"
                            class="w-full"
                        >
                            <Plus class="h-4 w-4 mr-2" />
                            Add New Topic
                        </Button>

                        <!-- Add Topic Form -->
                        <div v-else class="border rounded-lg p-4 space-y-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Topic Name</label>
                                <Input
                                    v-model="newTopicName"
                                    placeholder="e.g., Technical Support"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Description</label>
                                <Input
                                    v-model="newTopicDescription"
                                    placeholder="e.g., Technical issues and troubleshooting"
                                />
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    @click="createNewTopic"
                                    :disabled="isAddingTopic || !newTopicName.trim() || !newTopicDescription.trim()"
                                    size="sm"
                                    class="flex-1"
                                >
                                    <LoaderCircle v-if="isAddingTopic" class="h-4 w-4 mr-2 animate-spin" />
                                    Create & Add
                                </Button>
                                <Button
                                    @click="showAddForm = false"
                                    variant="outline"
                                    size="sm"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Topics List -->
                    <div class="flex-1 overflow-y-auto border rounded-lg">
                        <div v-if="loadingTopics" class="flex items-center justify-center py-12">
                            <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
                        </div>

                        <div v-else-if="filteredTopics.length === 0" class="text-center py-12 text-muted-foreground">
                            <p>No topics found</p>
                        </div>

                        <div v-else class="divide-y">
                            <div
                                v-for="topic in paginatedTopics"
                                :key="topic.id"
                                class="p-4 hover:bg-muted/50 transition-colors"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-medium text-sm">{{ topic.topic }}</h4>
                                            <Badge
                                                v-if="isTopicAssociated(topic.id)"
                                                variant="secondary"
                                                class="text-xs"
                                            >
                                                Active
                                            </Badge>
                                        </div>
                                        <p class="text-sm text-muted-foreground mt-1">{{ topic.description }}</p>
                                    </div>

                                    <!-- Add/Remove Button -->
                                    <Button
                                        v-if="isTopicAssociated(topic.id)"
                                        @click="removeTopicFromAgent(topic.id)"
                                        variant="outline"
                                        size="sm"
                                        class="flex-shrink-0"
                                    >
                                        <X class="h-4 w-4 mr-1" />
                                        Remove
                                    </Button>
                                    <Button
                                        v-else
                                        @click="addTopicToAgent(topic.id)"
                                        variant="default"
                                        size="sm"
                                        class="flex-shrink-0"
                                    >
                                        <Plus class="h-4 w-4 mr-1" />
                                        Add
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t">
                        <div class="text-sm text-muted-foreground">
                            Page {{ currentPage }} of {{ totalPages }} ({{ filteredTopics.length }} topics)
                        </div>
                        <div class="flex gap-2">
                            <Button
                                @click="currentPage--"
                                :disabled="currentPage === 1"
                                variant="outline"
                                size="sm"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button
                                @click="currentPage++"
                                :disabled="currentPage === totalPages"
                                variant="outline"
                                size="sm"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button @click="showTopicsDialog = false" variant="outline">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Info Dialog - What is Topics? -->
        <Dialog v-model:open="showInfoDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>What are Topics?</DialogTitle>
                    <DialogDescription>
                        Learn how topics help organize and analyze your conversations
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 text-sm">
                    <div>
                        <h3 class="font-semibold mb-2">📊 Overview</h3>
                        <p class="text-muted-foreground">
                            Topics are categories that help you understand what your users are talking about. 
                            Each conversation is automatically assigned one topic based on its content.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold mb-2">🎯 How it works</h3>
                        <ul class="space-y-2 text-muted-foreground list-disc list-inside">
                            <li>Conversations are analyzed and categorized into topics</li>
                            <li>You can customize which topics are available for your agent</li>
                            <li>Topics help you identify trends and common issues</li>
                            <li>Each conversation belongs to exactly one topic</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-semibold mb-2">💡 Examples</h3>
                        <div class="space-y-2 text-muted-foreground">
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
                        <h3 class="font-semibold mb-2">✨ Benefits</h3>
                        <ul class="space-y-2 text-muted-foreground list-disc list-inside">
                            <li>Track which topics are most popular</li>
                            <li>Identify areas that need more attention</li>
                            <li>Measure conversation volume by category</li>
                            <li>Improve your agent's knowledge base</li>
                        </ul>
                    </div>
                </div>

                <DialogFooter>
                    <Button @click="showInfoDialog = false">
                        Got it
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>

