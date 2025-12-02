<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
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
    MessageCircle, LoaderCircle, Info
} from 'lucide-vue-next';
import axios from 'axios';

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
    agent: App.Data.Resources.Agent.AgentResourceData
}

const props = defineProps<Props>();

const loading = ref(true);
const toneData = ref<any>(null);
const error = ref<string | null>(null);
const activeView = ref<'graph' | 'pie'>('graph');

const fetchToneData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('agents.tone.data', props.agent.id));
        toneData.value = response.data;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load tone data';
        console.error('Error fetching tone data:', err);
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
    if (!toneData.value) return null;

    return {
        labels: toneData.value.toneByDate.map((d: any) => formatDate(d.date)),
        datasets: [
            {
                label: '😐 Neutral',
                data: toneData.value.toneByDate.map((d: any) => d.neutral),
                borderColor: 'rgb(156, 163, 175)',
                backgroundColor: 'rgba(156, 163, 175, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'rgb(156, 163, 175)',
            },
            {
                label: '😤 Frustrated',
                data: toneData.value.toneByDate.map((d: any) => d.frustrated),
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'rgb(239, 68, 68)',
            },
            {
                label: '⚡ Urgent',
                data: toneData.value.toneByDate.map((d: any) => d.urgent),
                borderColor: 'rgb(251, 146, 60)',
                backgroundColor: 'rgba(251, 146, 60, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'rgb(251, 146, 60)',
            },
            {
                label: '😕 Confused',
                data: toneData.value.toneByDate.map((d: any) => d.confused),
                borderColor: 'rgb(168, 85, 247)',
                backgroundColor: 'rgba(168, 85, 247, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'rgb(168, 85, 247)',
            },
            {
                label: '😊 Satisfied',
                data: toneData.value.toneByDate.map((d: any) => d.satisfied),
                borderColor: 'rgb(74, 222, 128)',
                backgroundColor: 'rgba(74, 222, 128, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: 'rgb(74, 222, 128)',
            },
        ]
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
                }
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
    if (!toneData.value) return null;

    const totals = toneData.value.totalTones;

    return {
        labels: ['Neutral', 'Frustrated', 'Urgent', 'Confused', 'Satisfied', 'Unspecified'],
        datasets: [{
            data: [
                totals.neutral, 
                totals.frustrated, 
                totals.urgent, 
                totals.confused, 
                totals.satisfied, 
                totals.unspecified
            ],
            backgroundColor: [
                'rgb(156, 163, 175)',
                'rgb(239, 68, 68)',
                'rgb(251, 146, 60)',
                'rgb(168, 85, 247)',
                'rgb(74, 222, 128)',
                'rgb(203, 213, 225)',
            ],
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
                }
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
                    const total = toneData.value?.totalTones?.total || 0;
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                }
            }
        }
    }
};

// Summary stats
const summaryStats = computed(() => {
    if (!toneData.value) return null;

    const totals = toneData.value.totalTones;
    const total = totals.total;

    return {
        neutral: {
            count: totals.neutral,
            percentage: total > 0 ? ((totals.neutral / total) * 100).toFixed(1) : '0',
        },
        frustrated: {
            count: totals.frustrated,
            percentage: total > 0 ? ((totals.frustrated / total) * 100).toFixed(1) : '0',
        },
        satisfied: {
            count: totals.satisfied,
            percentage: total > 0 ? ((totals.satisfied / total) * 100).toFixed(1) : '0',
        },
        unspecified: {
            count: totals.unspecified,
            percentage: total > 0 ? ((totals.unspecified / total) * 100).toFixed(1) : '0',
        },
        total: total,
    };
});

onMounted(() => {
    fetchToneData();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Tone Analysis" description="Analyze user communication tone patterns and trends">
        <template #header-icon>
            <MessageCircle class="h-5 w-5" />
        </template>

        <!-- Delayed Data Notice -->
        <div class="mb-6 flex justify-end">
            <p class="text-sm text-muted-foreground">
                Analytics data for today might be delayed
            </p>
        </div>

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
                            @click="fetchToneData"
                            class="text-sm text-muted-foreground hover:text-foreground underline"
                        >
                            Try again
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Content -->
        <div v-else-if="toneData" class="space-y-6">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Neutral</p>
                                <Badge variant="secondary">
                                    {{ summaryStats?.neutral.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.neutral.count }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Frustrated</p>
                                <Badge class="bg-red-100 text-red-700 hover:bg-red-100">
                                    {{ summaryStats?.frustrated.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.frustrated.count }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Satisfied</p>
                                <Badge class="bg-green-100 text-green-700 hover:bg-green-100">
                                    {{ summaryStats?.satisfied.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.satisfied.count }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Unspecified</p>
                                <Badge variant="outline">
                                    {{ summaryStats?.unspecified.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.unspecified.count }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tone Chart -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CardTitle>Tone Distribution</CardTitle>
                            <Info class="h-4 w-4 text-muted-foreground" />
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
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>

