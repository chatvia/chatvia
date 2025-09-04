<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Users,
    Mail,
    Phone,
    Building2,
    Calendar,
    ExternalLink,
    MessageSquare,
    Maximize,
    Minimize,
    Search,
    Filter,
    Download,
    Plus,
    Tag,
    Clock,
    AlertTriangle,
    CheckCircle,
    Star,
    Eye,
    Settings
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface PipelineStage {
    id: number;
    name: string;
    slug: string;
    color: string;
    order: number;
    is_active: boolean;
    is_default: boolean;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Lead {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    company: string | null;
    message: string | null;
    pipeline_stage: string;
    status: string;
    priority: string;
    score: number;
    value: number | null;
    tags: string[] | null;
    assigned_to: number | null;
    source: string | null;
    notes: string | null;
    next_follow_up: string | null;
    follow_up_type: string | null;
    last_activity_at: string | null;
    created_at: string;
    agent: {
        id: number;
        name: string;
    };
    assigned_to_user?: User;
    status_color: string;
    status_label: string;
    priority_color: string;
    priority_label: string;
    display_name: string;
    initials: string;
}

interface Agent {
    id: number;
    name: string;
    type: string;
}

interface Analytics {
    total_leads: number;
    new_leads_today: number;
    conversion_rate: number;
    avg_response_time: number | null;
    leads_by_source: Record<string, number>;
    leads_by_priority: Record<string, number>;
}

interface Props {
    agent: Agent;
    leads: Record<string, Lead[]>;
    pipelineStages: Record<string, string>;
    pipelineStagesData: PipelineStage[];
    teamMembers: User[];
    analytics: Analytics;
    filters: {
        search?: string;
        status?: string;
        priority?: string;
        assigned_to?: number;
        pipeline_stage?: string;
        tags?: string;
    };
}

const props = defineProps<Props>();

// Reactive state
const draggedLead = ref<Lead | null>(null);
const dragOverColumn = ref<string | null>(null);
const isFullscreen = ref<boolean>(false);
const showFilters = ref<boolean>(false);
const showLeadDetail = ref<boolean>(false);
const selectedLead = ref<Lead | null>(null);

// Form state for lead interactions
const newNote = ref('');
const newTag = ref('');

// Search and filter state
const searchQuery = ref<string>(props.filters.search || '');
const selectedStatus = ref<string>(props.filters.status || '');
const selectedPriority = ref<string>(props.filters.priority || '');
const selectedAssignee = ref<number | undefined>(props.filters.assigned_to);
const selectedPipelineStage = ref<string>(props.filters.pipeline_stage || '');
const selectedTags = ref<string>(props.filters.tags || '');

// Computed properties
const getLeadsForStage = (stage: string): Lead[] => {
    return props.leads[stage] || [];
};

const getTotalLeads = computed(() => {
    return Object.values(props.leads).flat().length;
});

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedStatus.value || selectedPriority.value ||
           selectedAssignee.value || selectedPipelineStage.value || selectedTags.value;
});

// Helper methods
const getLeadInitials = (lead: Lead): string => {
    return lead.initials || '?';
};

const getDisplayName = (lead: Lead): string => {
    return lead.display_name || 'Anonymous Lead';
};

const getPriorityIcon = (priority: string) => {
    switch (priority) {
        case 'urgent': return AlertTriangle;
        case 'high': return Star;
        default: return null;
    }
};

const getStageColor = (stage: string): string => {
    const stageData = props.pipelineStagesData.find(s => s.slug === stage);
    return stageData?.color || '#6B7280';
};

const isOverdue = (lead: Lead): boolean => {
    if (!lead.next_follow_up) return false;
    return new Date(lead.next_follow_up) < new Date();
};

const hasRecentActivity = (lead: Lead): boolean => {
    if (!lead.last_activity_at) return false;
    const daysSince = (new Date().getTime() - new Date(lead.last_activity_at).getTime()) / (1000 * 3600 * 24);
    return daysSince <= 7;
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

// Drag and drop methods
const onDragStart = (event: DragEvent, lead: Lead) => {
    draggedLead.value = lead;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/html', '');
    }
};

const onDragEnd = () => {
    draggedLead.value = null;
    dragOverColumn.value = null;
};

const onDragOver = (event: DragEvent, stage: string) => {
    event.preventDefault();
    dragOverColumn.value = stage;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onDragLeave = () => {
    dragOverColumn.value = null;
};

const onDrop = (event: DragEvent, newStage: string) => {
    event.preventDefault();
    dragOverColumn.value = null;

    if (draggedLead.value && draggedLead.value.pipeline_stage !== newStage) {
        updateLeadPipelineStage(draggedLead.value, newStage);
    }

    draggedLead.value = null;
};

// Lead management methods
const updateLeadPipelineStage = (lead: Lead, newStage: string) => {
    router.patch(route('agents.leads.update-pipeline-stage', { agent: props.agent.id, lead: lead.id }), {
        pipeline_stage: newStage
    }, {
        onSuccess: () => {
            const stageName = props.pipelineStages[newStage];
            toast.success(`Lead moved to ${stageName}`);
        },
        onError: () => {
            toast.error('Failed to update pipeline stage');
        }
    });
};

const viewLead = (lead: Lead) => {
    selectedLead.value = lead;
    showLeadDetail.value = true;
};

const assignLead = (userId: number) => {
    if (!selectedLead.value) return;

    router.patch(route('agents.leads.assign', { agent: props.agent.id, lead: selectedLead.value.id }), {
        assigned_to: userId
    }, {
        onSuccess: () => {
            toast.success('Lead assigned successfully');
            showLeadDetail.value = false;
        },
        onError: () => {
            toast.error('Failed to assign lead');
        }
    });
};

const addNote = () => {
    if (!newNote.value.trim() || !selectedLead.value) return;

    router.post(route('agents.leads.add-note', { agent: props.agent.id, lead: selectedLead.value.id }), {
        note: newNote.value
    }, {
        onSuccess: () => {
            newNote.value = '';
            toast.success('Note added successfully');
        },
        onError: () => {
            toast.error('Failed to add note');
        }
    });
};

const addTag = () => {
    if (!newTag.value.trim() || !selectedLead.value) return;

    router.post(route('agents.leads.add-tag', { agent: props.agent.id, lead: selectedLead.value.id }), {
        tag: newTag.value
    }, {
        onSuccess: () => {
            newTag.value = '';
            toast.success('Tag added successfully');
        },
        onError: () => {
            toast.error('Failed to add tag');
        }
    });
};

// Filter methods
const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchQuery.value) params.set('search', searchQuery.value);
    if (selectedStatus.value) params.set('status', selectedStatus.value);
    if (selectedPriority.value) params.set('priority', selectedPriority.value);
    if (selectedAssignee.value) params.set('assigned_to', selectedAssignee.value.toString());
    if (selectedPipelineStage.value) params.set('pipeline_stage', selectedPipelineStage.value);
    if (selectedTags.value) params.set('tags', selectedTags.value);

    router.visit(route('agents.leads', { agent: props.agent.id, ...Object.fromEntries(params) }));
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    selectedPriority.value = '';
    selectedAssignee.value = undefined;
    selectedPipelineStage.value = '';
    selectedTags.value = '';
    applyFilters();
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
};
</script>

<template>
    <AgentLayout :agent="agent" title="Leads">
        <Head title="Leads" />

        <div class="space-y-6">
            <!-- Analytics Header -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                                <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Total Leads</p>
                                <p class="text-2xl font-bold">{{ props.analytics.total_leads.toLocaleString() }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-100 dark:bg-green-900/20 rounded-lg">
                                <Plus class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">New Today</p>
                                <p class="text-2xl font-bold">{{ props.analytics.new_leads_today }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                                <CheckCircle class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Conversion Rate</p>
                                <p class="text-2xl font-bold">{{ props.analytics.conversion_rate }}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-orange-100 dark:bg-orange-900/20 rounded-lg">
                                <Clock class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Avg Response Time</p>
                                <p class="text-2xl font-bold">
                                    {{ props.analytics.avg_response_time ? `${props.analytics.avg_response_time}h` : 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">Leads</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage leads generated by {{ agent.name }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Users class="h-4 w-4" />
                        <span>{{ getTotalLeads }} total leads</span>
                    </div>

                    <!-- Filter Toggle -->
                    <Button
                        @click="showFilters = !showFilters"
                        variant="outline"
                        size="sm"
                        class="gap-2"
                        :class="{ 'bg-primary/10': hasActiveFilters }"
                    >
                        <Filter class="h-4 w-4" />
                        Filters
                        <Badge v-if="hasActiveFilters" variant="secondary" class="ml-1">
                            Active
                        </Badge>
                    </Button>

                    <Button
                        @click="toggleFullscreen"
                        variant="outline"
                        size="sm"
                        class="gap-2"
                    >
                        <component :is="isFullscreen ? Minimize : Maximize" class="h-4 w-4" />
                        {{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}
                    </Button>
                </div>
            </div>

            <!-- Filters Panel -->
            <Card v-if="showFilters" class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search leads..."
                                class="pl-10"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- Priority Filter -->
                    <Select v-model="selectedPriority">
                        <SelectTrigger>
                            <SelectValue placeholder="Priority" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Priorities</SelectItem>
                            <SelectItem value="urgent">Urgent</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="low">Low</SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Assignee Filter -->
                    <Select v-model="selectedAssignee">
                        <SelectTrigger>
                            <SelectValue placeholder="Assignee" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="undefined">All Team Members</SelectItem>
                            <SelectItem v-for="member in props.teamMembers" :key="member.id" :value="member.id">
                                {{ member.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Pipeline Stage Filter -->
                    <Select v-model="selectedPipelineStage">
                        <SelectTrigger>
                            <SelectValue placeholder="Stage" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Stages</SelectItem>
                            <SelectItem v-for="(name, slug) in props.pipelineStages" :key="slug" :value="slug">
                                {{ name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Filter Actions -->
                    <div class="flex gap-2">
                        <Button @click="applyFilters" size="sm" class="flex-1">
                            Apply
                        </Button>
                        <Button @click="clearFilters" variant="outline" size="sm" class="flex-1">
                            Clear
                        </Button>
                    </div>
                </div>
            </Card>

            <!-- Kanban Board Card Container -->
            <Card class="p-0 overflow-hidden"
                :class="[
                    isFullscreen ? 'fixed inset-6 z-50 h-[calc(100vh-3rem)]' : ''
                ]"
            >

                <!-- Fullscreen Header -->
                <div v-if="isFullscreen" class="flex items-center justify-between p-6 border-b">
                    <div>
                        <h2 class="text-xl font-semibold text-foreground">{{ agent.name }} - Leads</h2>
                        <p class="text-sm text-muted-foreground">{{ getTotalLeads }} total leads</p>
                    </div>
                    <Button
                        @click="toggleFullscreen"
                        variant="outline"
                        size="sm"
                        class="gap-2"
                    >
                        <Minimize class="h-4 w-4" />
                        Exit Fullscreen
                    </Button>
                </div>

                <!-- Kanban Columns Container -->
                <ScrollArea
                    :class="[
                        'w-full whitespace-nowrap',
                        isFullscreen ? 'h-[calc(100vh-15rem)]' : 'h-[calc(100vh-16rem)]'
                    ]"
                >
                    <div class="flex w-max space-x-6 p-4">
                        <div
                            v-for="stage in props.pipelineStagesData"
                            :key="stage.slug"
                            :class="[
                                'flex flex-col w-80 h-full bg-muted/30 rounded-lg border transition-all duration-200 shrink-0',
                                dragOverColumn === stage.slug && draggedLead?.pipeline_stage !== stage.slug
                                    ? 'ring-2 ring-primary shadow-lg scale-105'
                                    : ''
                            ]"
                        >
                    <!-- Column Header -->
                    <div class="flex items-center justify-between p-4 border-b bg-card/50 rounded-t-lg">
                        <div class="flex items-center gap-2">
                            <div
                                class="w-3 h-3 rounded-full"
                                :style="{ backgroundColor: stage.color }"
                            ></div>
                            <h3 class="font-semibold text-sm uppercase tracking-wide text-muted-foreground">
                                {{ stage.name }}
                            </h3>
                        </div>
                        <Badge variant="secondary" class="h-5 px-2 text-xs font-medium">
                            {{ getLeadsForStage(stage.slug).length }}
                        </Badge>
                    </div>

                    <!-- Drop Zone -->
                    <div
                        class="flex-1 p-3 space-y-3 transition-all duration-200"
                        @dragover="onDragOver($event, stage.slug)"
                        @dragleave="onDragLeave"
                        @drop="onDrop($event, stage.slug)"
                        :class="[
                            dragOverColumn === stage.slug && draggedLead?.pipeline_stage !== stage.slug
                                ? 'bg-primary/5 border-2 border-dashed border-primary/50 rounded-md'
                                : ''
                        ]"
                    >
                            <!-- Lead Cards -->
                            <Card
                                v-for="lead in getLeadsForStage(stage.slug)"
                                :key="lead.id"
                                draggable="true"
                                @dragstart="onDragStart($event, lead)"
                                @dragend="onDragEnd"
                                @click="viewLead(lead)"
                                :class="[
                                    'cursor-pointer transition-all duration-200 hover:shadow-md group',
                                    draggedLead?.id === lead.id ? 'opacity-60 rotate-2 shadow-lg cursor-grabbing' : 'hover:scale-[1.02]'
                                ]"
                            >
                                <CardHeader class="pb-3">
                                    <div class="flex items-start gap-3">
                                        <Avatar class="h-8 w-8 flex-shrink-0">
                                            <AvatarFallback
                                                class="text-xs font-medium text-white"
                                                :style="{ backgroundColor: getStageColor(lead.pipeline_stage) }"
                                            >
                                                {{ getLeadInitials(lead) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <CardTitle class="text-sm font-medium truncate">
                                                    {{ getDisplayName(lead) }}
                                                </CardTitle>
                                                <!-- Lead Score -->
                                                <Badge
                                                    v-if="lead.score > 0"
                                                    variant="secondary"
                                                    class="text-xs px-1.5 py-0.5"
                                                >
                                                    {{ Math.round(lead.score) }}
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground truncate mt-1">
                                                {{ lead.company || 'No company' }}
                                            </p>

                                            <!-- Lead Value -->
                                            <div v-if="lead.value" class="text-xs font-medium text-green-600 dark:text-green-400 mt-1">
                                                ${{ lead.value.toLocaleString() }}
                                            </div>
                                        </div>

                                        <!-- Priority Icon -->
                                        <component
                                            v-if="getPriorityIcon(lead.priority)"
                                            :is="getPriorityIcon(lead.priority)"
                                            :class="[
                                                'h-4 w-4',
                                                lead.priority === 'urgent' ? 'text-red-500' : 'text-orange-500'
                                            ]"
                                        />
                                    </div>
                                </CardHeader>

                                <CardContent class="pt-0 space-y-3">
                                    <!-- Tags -->
                                    <div v-if="lead.tags && lead.tags.length > 0" class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="tag in lead.tags.slice(0, 2)"
                                            :key="tag"
                                            variant="outline"
                                            class="text-xs px-2 py-0.5"
                                        >
                                            {{ tag }}
                                        </Badge>
                                        <Badge
                                            v-if="lead.tags.length > 2"
                                            variant="outline"
                                            class="text-xs px-2 py-0.5"
                                        >
                                            +{{ lead.tags.length - 2 }}
                                        </Badge>
                                    </div>

                                    <!-- Contact Info -->
                                    <div class="space-y-2">
                                        <div v-if="lead.email" class="flex items-center gap-2 text-xs">
                                            <Mail class="h-3 w-3 flex-shrink-0 text-muted-foreground" />
                                            <span class="truncate text-muted-foreground">{{ lead.email }}</span>
                                        </div>
                                        <div v-if="lead.phone" class="flex items-center gap-2 text-xs">
                                            <Phone class="h-3 w-3 flex-shrink-0 text-muted-foreground" />
                                            <span class="text-muted-foreground">{{ lead.phone }}</span>
                                        </div>
                                    </div>

                                    <!-- Assignment Info -->
                                    <div v-if="lead.assigned_to_user" class="flex items-center gap-2 text-xs">
                                        <Users class="h-3 w-3 flex-shrink-0 text-muted-foreground" />
                                        <span class="text-muted-foreground">{{ lead.assigned_to_user.name }}</span>
                                    </div>

                                    <!-- Follow-up Info -->
                                    <div v-if="lead.next_follow_up" class="flex items-center gap-2 text-xs">
                                        <Clock class="h-3 w-3 flex-shrink-0 text-muted-foreground" />
                                        <span :class="[
                                            'text-muted-foreground',
                                            isOverdue(lead) ? 'text-red-500 font-medium' : ''
                                        ]">
                                            {{ formatDate(lead.next_follow_up) }}
                                        </span>
                                    </div>

                                    <!-- ChatMessage Preview -->
                                    <div v-if="lead.message">
                                        <Separator class="my-2" />
                                        <div class="flex items-start gap-2">
                                            <MessageSquare class="h-3 w-3 flex-shrink-0 mt-0.5 text-muted-foreground" />
                                            <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                                                {{ lead.message }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Date -->
                                    <div class="flex items-center justify-center pt-2 text-xs text-muted-foreground">
                                        <div class="flex items-center gap-1">
                                            <Calendar class="h-3 w-3" />
                                            <span>{{ formatDate(lead.created_at) }}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Empty State -->
                            <div
                                v-if="getLeadsForStage(stage.slug).length === 0"
                                class="flex flex-col items-center justify-center h-96 text-center"
                            >
                                <div
                                    class="w-12 h-12 rounded-full mb-3 flex items-center justify-center opacity-20"
                                    :style="{ backgroundColor: stage.color }"
                                >
                                    <Users class="h-6 w-6 text-white" />
                                </div>
                                <p class="text-sm text-muted-foreground mb-1">
                                    No {{ stage.name.toLowerCase() }} leads
                                </p>
                                <p class="text-xs text-muted-foreground/60">
                                    Drag leads here to update stage
                                </p>
                            </div>
                    </div>
                        </div>
                    </div>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            </Card>

            <!-- Lead Detail Modal -->
            <Dialog v-model:open="showLeadDetail">
                <DialogContent class="max-w-4xl max-h-[90vh] overflow-hidden">
                    <DialogHeader v-if="selectedLead">
                        <DialogTitle class="flex items-center gap-4 pb-4 border-b">
                            <Avatar class="h-12 w-12">
                                <AvatarFallback
                                    class="text-sm font-semibold text-white"
                                    :style="{ backgroundColor: getStageColor(selectedLead.pipeline_stage) }"
                                >
                                    {{ getLeadInitials(selectedLead) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold">{{ getDisplayName(selectedLead) }}</h2>
                                <p class="text-muted-foreground">{{ selectedLead.company || selectedLead.email }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <Badge variant="outline">{{ props.pipelineStages[selectedLead.pipeline_stage] }}</Badge>
                                    <Badge
                                        :variant="selectedLead.priority === 'urgent' ? 'destructive' : 'secondary'"
                                        class="gap-1"
                                    >
                                        <component
                                            v-if="getPriorityIcon(selectedLead.priority)"
                                            :is="getPriorityIcon(selectedLead.priority)"
                                            class="h-3 w-3"
                                        />
                                        {{ selectedLead.priority_label }}
                                    </Badge>
                                    <Badge v-if="selectedLead.score > 0" variant="secondary">
                                        Score: {{ Math.round(selectedLead.score) }}
                                    </Badge>
                                </div>
                            </div>
                            <div v-if="selectedLead.value" class="text-right">
                                <p class="text-sm text-muted-foreground">Potential Value</p>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    ${{ selectedLead.value.toLocaleString() }}
                                </p>
                            </div>
                        </DialogTitle>
                    </DialogHeader>

                    <div v-if="selectedLead" class="mt-6">
                        <Tabs default-value="overview" class="w-full">
                            <TabsList class="grid w-full grid-cols-4">
                                <TabsTrigger value="overview">Overview</TabsTrigger>
                                <TabsTrigger value="contact">Contact</TabsTrigger>
                                <TabsTrigger value="notes">Notes</TabsTrigger>
                                <TabsTrigger value="activity">Activity</TabsTrigger>
                            </TabsList>

                            <div class="mt-6 max-h-[50vh] overflow-y-auto">
                                <!-- Overview Tab -->
                                <TabsContent value="overview" class="space-y-6">
                                    <!-- Tags -->
                                    <div class="space-y-3">
                                        <h3 class="font-semibold">Tags</h3>
                                        <div v-if="selectedLead.tags && selectedLead.tags.length > 0" class="flex flex-wrap gap-2">
                                            <Badge
                                                v-for="tag in selectedLead.tags"
                                                :key="tag"
                                                variant="outline"
                                            >
                                                {{ tag }}
                                            </Badge>
                                        </div>

                                        <!-- Add Tag -->
                                        <div class="flex gap-2">
                                            <Input
                                                v-model="newTag"
                                                placeholder="Add tag..."
                                                @keyup.enter="addTag"
                                                class="text-sm"
                                            />
                                            <Button @click="addTag" size="sm" variant="outline">
                                                <Plus class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Assignment -->
                                    <div class="space-y-3">
                                        <h3 class="font-semibold">Assignment</h3>
                                        <Select :model-value="selectedLead.assigned_to" @update:model-value="assignLead">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Assign to team member" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem :value="null">Unassigned</SelectItem>
                                                <SelectItem v-for="member in props.teamMembers" :key="member.id" :value="member.id">
                                                    {{ member.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </TabsContent>

                                <!-- Contact Tab -->
                                <TabsContent value="contact" class="space-y-4">
                                    <div v-if="selectedLead.email" class="flex items-center gap-3 p-4 bg-muted/20 rounded-lg">
                                        <Mail class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p class="font-medium">{{ selectedLead.email }}</p>
                                            <p class="text-sm text-muted-foreground">Email Address</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedLead.phone" class="flex items-center gap-3 p-4 bg-muted/20 rounded-lg">
                                        <Phone class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p class="font-medium">{{ selectedLead.phone }}</p>
                                            <p class="text-sm text-muted-foreground">Phone Number</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedLead.company" class="flex items-center gap-3 p-4 bg-muted/20 rounded-lg">
                                        <Building2 class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p class="font-medium">{{ selectedLead.company }}</p>
                                            <p class="text-sm text-muted-foreground">Company</p>
                                        </div>
                                    </div>
                                </TabsContent>

                                <!-- Notes Tab -->
                                <TabsContent value="notes" class="space-y-4">
                                    <!-- Existing Notes -->
                                    <div v-if="selectedLead.notes">
                                        <h3 class="font-semibold mb-3">Current Notes</h3>
                                        <div class="p-4 bg-muted/20 rounded-lg">
                                            <p class="text-sm whitespace-pre-wrap">{{ selectedLead.notes }}</p>
                                        </div>
                                    </div>

                                    <!-- Add Note -->
                                    <div>
                                        <h3 class="font-semibold mb-3">Add Note</h3>
                                        <div class="space-y-3">
                                            <textarea
                                                v-model="newNote"
                                                placeholder="Add a note..."
                                                rows="4"
                                                class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            ></textarea>
                                            <Button @click="addNote" class="w-full gap-2">
                                                <Plus class="h-4 w-4" />
                                                Add Note
                                            </Button>
                                        </div>
                                    </div>
                                </TabsContent>

                                <!-- Activity Tab -->
                                <TabsContent value="activity" class="space-y-4">
                                    <!-- Original ChatMessage -->
                                    <div v-if="selectedLead.message">
                                        <h3 class="font-semibold mb-3">Original Message</h3>
                                        <div class="p-4 bg-muted/20 rounded-lg">
                                            <p class="text-sm leading-relaxed">{{ selectedLead.message }}</p>
                                        </div>
                                    </div>

                                    <!-- Activity placeholder -->
                                    <div class="text-center py-12 text-muted-foreground">
                                        <Clock class="h-12 w-12 mx-auto mb-4 opacity-30" />
                                        <h3 class="font-medium mb-2">Activity Timeline</h3>
                                        <p class="text-sm">Detailed activity tracking coming soon</p>
                                    </div>
                                </TabsContent>
                            </div>
                        </Tabs>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AgentLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
