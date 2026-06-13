<script lang="ts" setup>
import AnalyticsDisabled from '@/components/AnalyticsDisabled.vue';
import LockedFeature from '@/components/LockedFeature.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import { ChevronLeft, ChevronRight, Info, LoaderCircle, Maximize2, MessageSquare, Plus, Search, Sparkles, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Line, Pie } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, ChartTooltip, Legend, Filler);

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    // Per-agent on/off switch (Setup → Settings). Independent of the plan
    // capability below: the plan must allow it AND the owner must not have
    // switched it off.
    enabled: boolean;
    // Master switch: when the chat log is off there are no stored messages to
    // analyze, so analytics can't be enabled until it's turned back on.
    chatlogEnabled: boolean;
}

const props = defineProps<Props>();

const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('topics'));

// Local mirror of the per-agent switch so flipping it on from the disabled
// state swaps straight to the charts without a full page reload.
const isEnabled = ref(props.enabled);
const enabling = ref(false);

const enableTopics = async () => {
    if (!props.chatlogEnabled) return;
    enabling.value = true;
    try {
        await axios.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'analytics' }), { topics: true });
        isEnabled.value = true;
        fetchTopicsData();
        fetchSuggestions();
    } catch (err) {
        console.error('Could not enable topic analysis:', err);
    } finally {
        enabling.value = false;
    }
};

const loading = ref(true);
const topicsData = ref<Domain.Conversation.Topic.Data.TopicsResourceData | null>(null);
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
        error.value = err.response?.data?.message || t('agents.topics_index.load_error');
        console.error('Error fetching topics data:', err);
    } finally {
        loading.value = false;
    }
};

// --- Suggested (discovered) topics -------------------------------------------
interface TopicSuggestion {
    label: string;
    count: number;
    examples: { conversation_id: string; title: string | null }[];
}

const suggestions = ref<TopicSuggestion[]>([]);
const promotingLabel = ref<string | null>(null);
const dismissTarget = ref<string | null>(null);
const dismissing = ref(false);

const fetchSuggestions = async () => {
    try {
        const response = await axios.get(route('agents.topics.suggestions', props.agent.external_id));
        suggestions.value = response.data.suggestions ?? [];
    } catch (err: any) {
        console.error('Error fetching topic suggestions:', err);
    }
};

const promoteSuggestion = async (label: string) => {
    promotingLabel.value = label;
    try {
        await axios.post(route('agents.topics.suggestions.promote', props.agent.external_id), { label });
        await Promise.all([fetchSuggestions(), fetchTopicsData()]);
        toast.success(t('agents.topics_index.promoted', { label }));
    } catch (err: any) {
        toast.error(err.response?.data?.message || t('agents.topics_index.promote_error'));
    } finally {
        promotingLabel.value = null;
    }
};

const confirmDismiss = async () => {
    if (!dismissTarget.value) return;

    dismissing.value = true;
    try {
        await axios.delete(route('agents.topics.suggestions.dismiss', props.agent.external_id), {
            data: { label: dismissTarget.value },
        });
        suggestions.value = suggestions.value.filter((s) => s.label !== dismissTarget.value);
        toast.success(t('agents.topics_index.dismissed'));
    } catch (err: any) {
        toast.error(err.response?.data?.message || t('agents.topics_index.dismiss_error'));
    } finally {
        dismissing.value = false;
        dismissTarget.value = null;
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

    const datasets = (topicsData.value.topics_by_date as Domain.Conversation.Topic.Data.TopicTrendData[]).map(
        (topic: Domain.Conversation.Topic.Data.TopicTrendData, index: number) => {
            const color = colors[index % colors.length];

            return {
                label: topic.topic_name,
                data: topic.counts.map((d: Domain.Conversation.Topic.Data.TopicCountByDateData) => d.count),
                borderColor: color,
                backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            };
        },
    );

    const firstTopic = (topicsData.value.topics_by_date as Domain.Conversation.Topic.Data.TopicTrendData[])[0];
    const labels = firstTopic ? firstTopic.counts.map((d: Domain.Conversation.Topic.Data.TopicCountByDateData) => formatDate(d.date)) : [];

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

    const totals = topicsData.value.total_topics as Domain.Conversation.Topic.Data.TopicSummaryData[];

    return {
        labels: totals.map((t: Domain.Conversation.Topic.Data.TopicSummaryData) => t.name),
        datasets: [
            {
                data: totals.map((t: Domain.Conversation.Topic.Data.TopicSummaryData) => t.count),
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
                    const total =
                        (topicsData.value?.total_topics as Domain.Conversation.Topic.Data.TopicSummaryData[] | undefined)?.reduce(
                            (sum: number, t: Domain.Conversation.Topic.Data.TopicSummaryData) => sum + t.count,
                            0,
                        ) ?? 0;
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
        toast.error(t('agents.topics_index.load_topics_error'));
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

        toast.success(t('agents.topics_index.added'));
    } catch (err: any) {
        console.error('Error adding topic:', err);
        toast.error(err.response?.data?.message || t('agents.topics_index.add_error'));
    }
};

// Remove topic from agent
const removeTopicFromAgent = async (topicId: number) => {
    try {
        await axios.delete(route('agents.topics.destroy', { agent: props.agent.external_id, topic: topicId }));

        // Refresh topics
        await fetchAllTopics();
        await fetchTopicsData();

        toast.success(t('agents.topics_index.removed'));
    } catch (err: any) {
        console.error('Error removing topic:', err);
        toast.error(err.response?.data?.message || t('agents.topics_index.remove_error'));
    }
};

// Create new topic
const createNewTopic = async () => {
    if (!newTopicName.value.trim() || !newTopicDescription.value.trim()) {
        toast.error(t('agents.topics_index.fill_fields'));
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

        toast.success(t('agents.topics_index.created'));
    } catch (err: any) {
        console.error('Error creating topic:', err);
        toast.error(err.response?.data?.message || t('agents.topics_index.create_error'));
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
    if (hasAccess.value && props.chatlogEnabled && isEnabled.value) {
        fetchTopicsData();
        fetchSuggestions();
    }
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.topics_index.title')" :description="t('agents.topics_index.description')">
        <template #header-icon>
            <MessageSquare class="h-5 w-5" />
        </template>

        <LockedFeature v-if="!hasAccess" :description="t('agents.topics_index.locked_description')" />

        <!-- Plan allows it, but the owner switched it off for this agent -->
        <AnalyticsDisabled
            v-else-if="!chatlogEnabled || !isEnabled"
            :title="t('agents.topics_index.disabled_title')"
            :description="t('agents.topics_index.disabled_description')"
            :can-enable="chatlogEnabled"
            :blocked-reason="t('agents.topics_index.blocked_reason')"
            :enabling="enabling"
            @enable="enableTopics"
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
                            <button @click="fetchTopicsData" class="text-muted-foreground hover:text-foreground text-sm underline">
                                {{ t('agents.topics_index.try_again') }}
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Content -->
            <div v-else-if="topicsData">
                <!-- Suggested (discovered) topics -->
                <Card v-if="suggestions.length > 0" class="mb-6">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Sparkles class="text-primary h-4 w-4" />
                            <CardTitle>{{ t('agents.topics_index.suggested_title') }}</CardTitle>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            {{ t('agents.topics_index.suggested_description') }}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="s in suggestions" :key="s.label" class="flex items-start justify-between gap-4 rounded-lg border p-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium">{{ s.label }}</span>
                                        <Badge variant="secondary" class="text-xs">
                                            {{ s.count }} {{ t('agents.topics_index.conversation', s.count) }}
                                        </Badge>
                                    </div>
                                    <p v-if="s.examples.length" class="text-muted-foreground mt-1 truncate text-xs">
                                        e.g.
                                        {{
                                            s.examples
                                                .slice(0, 3)
                                                .map((e) => e.title || t('agents.topics_index.untitled'))
                                                .join(' · ')
                                        }}
                                    </p>
                                </div>
                                <div class="flex flex-shrink-0 gap-2">
                                    <Button @click="promoteSuggestion(s.label)" :disabled="promotingLabel === s.label" size="sm">
                                        <LoaderCircle v-if="promotingLabel === s.label" class="me-1 h-4 w-4 animate-spin" />
                                        <Plus v-else class="me-1 h-4 w-4" />
                                        {{ t('agents.topics_index.add') }}
                                    </Button>
                                    <Button @click="dismissTarget = s.label" variant="outline" size="sm">
                                        <X class="me-1 h-4 w-4" />
                                        {{ t('agents.topics_index.dismiss') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Topics Chart with Sidebar -->
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <CardTitle>{{ t('agents.topics_index.chart_title') }}</CardTitle>
                                <button @click="showInfoDialog = true" class="text-muted-foreground hover:text-foreground transition-colors">
                                    <Info class="h-4 w-4 cursor-pointer" />
                                </button>
                            </div>
                            <Tabs v-model="activeView" class="w-auto">
                                <TabsList>
                                    <TabsTrigger value="graph" @click="activeView = 'graph'">{{ t('agents.topics_index.graph') }}</TabsTrigger>
                                    <TabsTrigger value="pie" @click="activeView = 'pie'">{{ t('agents.topics_index.pie_chart') }}</TabsTrigger>
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
                            <div class="w-full border-t pt-6 lg:w-80 lg:border-s lg:border-t-0 lg:ps-6 lg:pt-0">
                                <div class="flex h-96 flex-col space-y-4">
                                    <!-- Total Topics -->
                                    <div>
                                        <div class="text-3xl font-bold">{{ topicsData.total_count }}</div>
                                        <div class="text-muted-foreground text-sm">{{ t('agents.topics_index.total_topics') }}</div>
                                    </div>

                                    <!-- Topics List -->
                                    <div
                                        v-if="topicsData.total_topics.length === 0"
                                        class="text-muted-foreground flex flex-1 items-center justify-center"
                                    >
                                        <p>{{ t('agents.topics_index.no_data') }}</p>
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
                                            <span class="ms-2 text-sm font-semibold">{{ topic.count }}</span>
                                        </div>
                                    </div>

                                    <!-- View All Button at Bottom -->
                                    <div class="border-t pt-2">
                                        <Button variant="outline" size="sm" class="w-full" @click="openTopicsDialog">
                                            <span class="flex items-center justify-center gap-2">
                                                {{ t('agents.topics_index.view_all') }}
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
                        <DialogTitle>{{ t('agents.topics_index.manage_title') }}</DialogTitle>
                        <DialogDescription>{{ t('agents.topics_index.manage_description') }}</DialogDescription>
                    </DialogHeader>

                    <div class="flex flex-1 flex-col space-y-4 overflow-hidden">
                        <div class="relative">
                            <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
                            <Input v-model="searchQuery" :placeholder="t('agents.topics_index.search_placeholder')" class="ps-10" />
                        </div>

                        <div class="space-y-3">
                            <Button v-if="!showAddForm" @click="showAddForm = true" variant="outline" size="sm" class="w-full">
                                <Plus class="me-2 h-4 w-4" />
                                {{ t('agents.topics_index.add_new') }}
                            </Button>

                            <div v-else class="space-y-3 rounded-lg border p-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">{{ t('agents.topics_index.topic_name') }}</label>
                                    <Input v-model="newTopicName" :placeholder="t('agents.topics_index.topic_name_placeholder')" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">{{ t('agents.topics_index.description_label') }}</label>
                                    <Input v-model="newTopicDescription" :placeholder="t('agents.topics_index.description_placeholder')" />
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        @click="createNewTopic"
                                        :disabled="isAddingTopic || !newTopicName.trim() || !newTopicDescription.trim()"
                                        size="sm"
                                        class="flex-1"
                                    >
                                        <LoaderCircle v-if="isAddingTopic" class="me-2 h-4 w-4 animate-spin" />
                                        {{ t('agents.topics_index.create_add') }}
                                    </Button>
                                    <Button @click="showAddForm = false" variant="outline" size="sm">{{ t('common.cancel') }}</Button>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto rounded-lg border">
                            <div v-if="loadingTopics" class="flex items-center justify-center py-12">
                                <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                            </div>

                            <div v-else-if="filteredTopics.length === 0" class="text-muted-foreground py-12 text-center">
                                <p>{{ t('agents.topics_index.no_topics_found') }}</p>
                            </div>

                            <div v-else class="divide-y">
                                <div v-for="topic in paginatedTopics" :key="topic.id" class="hover:bg-muted/50 p-4 transition-colors">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-medium">{{ topic.topic }}</h4>
                                                <Badge v-if="isTopicAssociated(topic.id)" variant="secondary" class="text-xs">{{
                                                    t('agents.topics_index.active')
                                                }}</Badge>
                                            </div>
                                            <p class="text-muted-foreground mt-1 text-sm">{{ topic.description }}</p>
                                        </div>

                                        <Button
                                            v-if="isTopicAssociated(topic.id)"
                                            @click="removeTopicFromAgent(topic.id)"
                                            variant="outline"
                                            size="sm"
                                            class="shrink-0"
                                        >
                                            <X class="me-1 h-4 w-4" />
                                            {{ t('agents.topics_index.remove') }}
                                        </Button>
                                        <Button v-else @click="addTopicToAgent(topic.id)" variant="default" size="sm" class="shrink-0">
                                            <Plus class="me-1 h-4 w-4" />
                                            {{ t('agents.topics_index.add') }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="totalPages > 1" class="flex items-center justify-between border-t px-4 py-3">
                            <div class="text-muted-foreground text-sm">
                                {{ t('agents.topics_index.page_of', { current: currentPage, last: totalPages, total: filteredTopics.length }) }}
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
                        <Button @click="showTopicsDialog = false" variant="outline">{{ t('agents.topics_index.close') }}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Info Dialog - What is Topics? -->
            <Dialog v-model:open="showInfoDialog">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ t('agents.topics_index.info_title') }}</DialogTitle>
                        <DialogDescription>{{ t('agents.topics_index.info_description') }}</DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 text-sm">
                        <div>
                            <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_overview_title') }}</h3>
                            <p class="text-muted-foreground">
                                {{ t('agents.topics_index.info_overview') }}
                            </p>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_how_title') }}</h3>
                            <ul class="text-muted-foreground list-inside list-disc space-y-2">
                                <li>{{ t('agents.topics_index.info_how_1') }}</li>
                                <li>{{ t('agents.topics_index.info_how_2') }}</li>
                                <li>{{ t('agents.topics_index.info_how_3') }}</li>
                                <li>{{ t('agents.topics_index.info_how_4') }}</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_examples_title') }}</h3>
                            <div class="text-muted-foreground space-y-2">
                                <div class="flex items-start gap-2">
                                    <span class="text-primary">•</span>
                                    <span>{{ t('agents.topics_index.info_example_1') }}</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-primary">•</span>
                                    <span>{{ t('agents.topics_index.info_example_2') }}</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-primary">•</span>
                                    <span>{{ t('agents.topics_index.info_example_3') }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_benefits_title') }}</h3>
                            <ul class="text-muted-foreground list-inside list-disc space-y-2">
                                <li>{{ t('agents.topics_index.info_benefits_1') }}</li>
                                <li>{{ t('agents.topics_index.info_benefits_2') }}</li>
                                <li>{{ t('agents.topics_index.info_benefits_3') }}</li>
                                <li>{{ t('agents.topics_index.info_benefits_4') }}</li>
                            </ul>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button @click="showInfoDialog = false">{{ t('agents.topics_index.got_it') }}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Dismiss suggestion confirmation -->
            <AlertDialog
                :open="dismissTarget !== null"
                @update:open="
                    (v) => {
                        if (!v) dismissTarget = null;
                    }
                "
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('agents.topics_index.dismiss_dialog_title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {{ t('agents.topics_index.dismiss_dialog_description', { label: dismissTarget }) }}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="dismissing">{{ t('common.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction :disabled="dismissing" @click="confirmDismiss">{{ t('agents.topics_index.dismiss') }}</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </template>
    </AgentLayout>
</template>
