<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import IngestionProgressBar from '@/components/ui/IngestionProgressBar.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, CheckCircle, ChevronDown, Edit3, HelpCircle, LoaderCircle, Plus, Save, Trash2, Unlink } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

interface Question {
    id: string;
    question: string;
}

interface QAEntry {
    id: number;
    title: string;
    questions: Question[];
    answer: string;
    status: 'pending' | 'ingesting' | 'ingested' | 'failed';
    created_at: string;
    updated_at: string;
    progress?: number;
    agents_count?: number;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { hasPermission } = useTeamPermissions();

// Dates follow the user's chosen format (Settings → Date & time).
const { formatDate } = useDateFormat();

// Check if user can delete sources
const canDeleteSources = computed(() => hasPermission('agent.sources.delete'));

// Q&A creation is synchronous (a single DB insert with no chunking or embedding),
// so there's no ingestion progress to broadcast. No websocket listeners needed.

// State
const qaEntries = ref<QAEntry[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const showCreateForm = ref(false);
const editingEntry = ref<QAEntry | null>(null);

// Delete confirmation dialog
const deleteDialogOpen = ref(false);
const entryToDelete = ref<QAEntry | null>(null);
const isUnlinking = ref(false);
const isDeleting = ref(false);

// Form for creating/editing QA entries
const form = useForm({
    title: '',
    questions: [{ id: crypto.randomUUID(), question: '' }] as Question[],
    answer: '',
});

// Methods
const openCreateForm = () => {
    form.reset();
    form.questions = [{ id: crypto.randomUUID(), question: '' }];
    editingEntry.value = null;
    showCreateForm.value = true;
};

const closeCreateForm = () => {
    showCreateForm.value = false;
    form.reset();
    form.questions = [{ id: crypto.randomUUID(), question: '' }];
    editingEntry.value = null;
};

const addQuestion = () => {
    form.questions.push({ id: crypto.randomUUID(), question: '' });
};

const removeQuestion = (questionId: string) => {
    if (form.questions.length > 1) {
        form.questions = form.questions.filter((q) => q.id !== questionId);
    }
};

const submitForm = () => {
    if (editingEntry.value) {
        updateEntry();
    } else {
        createEntry();
    }
};

const createEntry = () => {
    isSubmitting.value = true;

    form.post(route('agents.sources.qa.store', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateForm();
            fetchQAEntries();
            toast.success(t('agents.sources.qa.created'));
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            toast.error(t('agents.sources.qa.create_failed'));
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const editEntry = async (entry: QAEntry) => {
    try {
        isLoading.value = true;
        const response = await axios.get(
            route('agents.sources.qa.show', {
                agent: props.agent.external_id,
                id: entry.id,
            }),
        );

        const data = response.data;

        form.title = data.title;
        form.questions = data.questions || [{ id: crypto.randomUUID(), question: '' }];
        form.answer = data.answer;

        editingEntry.value = entry;
        showCreateForm.value = true;
    } catch (error) {
        console.error('Error fetching Q&A entry:', error);
        toast.error(t('agents.sources.qa.load_failed'));
    } finally {
        isLoading.value = false;
    }
};

const updateEntry = () => {
    if (!editingEntry.value) return;

    isSubmitting.value = true;

    form.patch(
        route('agents.sources.qa.update', {
            agent: props.agent.external_id,
            id: editingEntry.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateForm();
                fetchQAEntries();
                toast.success(t('agents.sources.qa.updated'));
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
                toast.error(t('agents.sources.qa.update_failed'));
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const confirmDelete = (entry: QAEntry) => {
    // Check permission first
    if (!canDeleteSources.value) {
        toast.error(t('agents.sources.common.permission_denied'), {
            description: t('agents.sources.common.permission_denied_description'),
        });
        return;
    }

    entryToDelete.value = entry;
    deleteDialogOpen.value = true;
};

const deleteEntry = async () => {
    if (!entryToDelete.value) return;

    try {
        isDeleting.value = true;

        await axios.delete(
            route('agents.sources.qa.destroy', {
                agent: props.agent.external_id,
                id: entryToDelete.value.id,
            }),
        );

        toast.success(t('agents.sources.qa.deleted'));
        fetchQAEntries();
    } catch (error) {
        console.error('Error deleting Q&A entry:', error);
        toast.error(t('agents.sources.qa.delete_failed'));
    } finally {
        isDeleting.value = false;
        deleteDialogOpen.value = false;
        entryToDelete.value = null;
    }
};

const unlinkEntry = async (entry: QAEntry) => {
    try {
        isUnlinking.value = true;

        await axios.post(
            route('agents.sources.qa.unlink', {
                agent: props.agent.external_id,
                id: entry.id,
            }),
        );

        toast.success(t('agents.sources.qa.unlinked'));
        fetchQAEntries();
    } catch (error) {
        console.error('Error unlinking Q&A entry:', error);
        toast.error(t('agents.sources.qa.unlink_failed'));
    } finally {
        isUnlinking.value = false;
    }
};

const fetchQAEntries = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get(route('agents.sources.qa.index', { agent: props.agent.external_id }));
        qaEntries.value = response.data.data || [];
    } catch (error) {
        console.error('Error fetching Q&A entries:', error);
        toast.error(t('agents.sources.qa.load_entries_failed'));
    } finally {
        isLoading.value = false;
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'ingested':
            return CheckCircle;
        case 'ingesting':
            return LoaderCircle;
        case 'failed':
            return AlertCircle;
        default:
            return LoaderCircle;
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'ingested':
            return 'text-green-500';
        case 'ingesting':
            return 'text-blue-500';
        case 'failed':
            return 'text-red-500';
        default:
            return 'text-gray-500';
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case 'ingested':
            return t('agents.sources.qa.status.ingested');
        case 'ingesting':
            return t('agents.sources.qa.status.ingesting');
        case 'failed':
            return t('agents.sources.qa.status.failed');
        default:
            return t('agents.sources.qa.status.pending');
    }
};

// Fetch entries on mount
fetchQAEntries();
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <!-- Header -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <HelpCircle class="h-5 w-5" />
                                {{ t('agents.sources.qa.title') }}
                            </CardTitle>
                            <CardDescription> {{ t('agents.sources.qa.description') }} </CardDescription>
                        </div>
                        <Button @click="openCreateForm" size="sm">
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('agents.sources.qa.add') }}
                        </Button>
                    </div>
                </CardHeader>
            </Card>

            <!-- Create/Edit Form -->
            <Card v-if="showCreateForm">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>{{ editingEntry ? t('agents.sources.qa.edit') : t('agents.sources.qa.add') }}</CardTitle>
                        <Button @click="closeCreateForm" variant="ghost" size="sm"> {{ t('agents.sources.common.cancel') }} </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Title -->
                    <div class="space-y-2">
                        <Label for="title">{{ t('agents.sources.qa.title_label') }}</Label>
                        <Input id="title" v-model="form.title" :placeholder="t('agents.sources.qa.title_placeholder')" :disabled="isSubmitting" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <!-- Questions -->
                    <div class="space-y-2">
                        <Label>{{ t('agents.sources.qa.questions_label') }}</Label>
                        <div v-for="question in form.questions" :key="question.id" class="flex gap-2">
                            <Input
                                v-model="question.question"
                                :placeholder="t('agents.sources.qa.question_placeholder')"
                                :disabled="isSubmitting"
                                class="flex-1"
                            />
                            <Button
                                v-if="form.questions.length > 1"
                                @click="removeQuestion(question.id)"
                                variant="ghost"
                                size="sm"
                                :disabled="isSubmitting"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <Button @click="addQuestion" variant="outline" size="sm" :disabled="isSubmitting">
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('agents.sources.qa.add_question') }}
                        </Button>
                        <InputError :message="form.errors.questions" />
                    </div>

                    <!-- Answer -->
                    <div class="space-y-2">
                        <Label for="answer">{{ t('agents.sources.qa.answer_label') }}</Label>
                        <Textarea
                            id="answer"
                            v-model="form.answer"
                            :placeholder="t('agents.sources.qa.answer_placeholder')"
                            rows="8"
                            :disabled="isSubmitting"
                        />
                        <div class="text-muted-foreground text-sm">{{ t('agents.sources.qa.char_count', { count: form.answer.length }) }}</div>
                        <InputError :message="form.errors.answer" />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end">
                        <Button
                            @click="submitForm"
                            :disabled="isSubmitting || !form.title || !form.answer || form.questions.every((q) => !q.question)"
                        >
                            <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                            <Save v-else class="me-2 h-4 w-4" />
                            {{ editingEntry ? t('agents.sources.qa.update') : t('agents.sources.qa.add') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Q&A Entries List -->
            <Card v-if="isLoading && qaEntries.length === 0">
                <CardContent class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                </CardContent>
            </Card>

            <Card v-else-if="qaEntries.length === 0 && !showCreateForm">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <HelpCircle class="text-muted-foreground mb-4 h-12 w-12" />
                    <h3 class="mb-2 text-lg font-medium">{{ t('agents.sources.qa.empty_title') }}</h3>
                    <p class="text-muted-foreground mb-4 max-w-sm">
                        {{ t('agents.sources.qa.empty_description') }}
                    </p>
                    <Button @click="openCreateForm">
                        <Plus class="me-2 h-4 w-4" />
                        {{ t('agents.sources.qa.add_first') }}
                    </Button>
                </CardContent>
            </Card>

            <!-- Q&A Entries -->
            <div v-else class="space-y-4">
                <Card v-for="entry in qaEntries" :key="entry.id">
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg">{{ entry.title }}</CardTitle>
                                <div class="mt-2 space-y-1">
                                    <p v-for="(question, index) in entry.questions" :key="index" class="text-muted-foreground text-sm">
                                        {{ t('agents.sources.qa.question_prefix', { number: index + 1 }) }} {{ question.question }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Status -->
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <component
                                                :is="getStatusIcon(entry.status)"
                                                :class="['h-5 w-5', getStatusColor(entry.status), { 'animate-spin': entry.status === 'ingesting' }]"
                                            />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{ getStatusText(entry.status) }}
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <!-- Actions Dropdown -->
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="sm">
                                            <ChevronDown class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem @click="editEntry(entry)">
                                                <Edit3 class="me-2 h-4 w-4" />
                                                {{ t('agents.sources.qa.edit_action') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="entry.agents_count && entry.agents_count > 1"
                                                @click="unlinkEntry(entry)"
                                                :disabled="isUnlinking"
                                            >
                                                <Unlink class="me-2 h-4 w-4" />
                                                {{ t('agents.sources.common.unlink') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="confirmDelete(entry)" class="text-red-600">
                                                <Trash2 class="me-2 h-4 w-4" />
                                                {{ t('agents.sources.common.delete') }}
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Progress Bar -->
                        <IngestionProgressBar
                            v-if="entry.status === 'ingesting' && entry.progress !== undefined"
                            :progress="entry.progress"
                            class="mb-4"
                        />

                        <!-- Answer Preview -->
                        <div class="text-muted-foreground text-sm">
                            <p class="line-clamp-3">{{ entry.answer }}</p>
                        </div>

                        <!-- Meta Info -->
                        <div class="text-muted-foreground mt-4 flex items-center gap-4 text-xs">
                            <span>{{ t('agents.sources.qa.created_at', { date: formatDate(entry.created_at) }) }}</span>
                            <span v-if="entry.agents_count && entry.agents_count > 1">
                                {{ t('agents.sources.qa.used_by_agents', { count: entry.agents_count }) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ t('agents.sources.qa.delete_title') }}</DialogTitle>
                        <DialogDescription> {{ t('agents.sources.qa.delete_description') }} </DialogDescription>
                    </DialogHeader>
                    <div class="mt-4 flex justify-end gap-2">
                        <Button @click="deleteDialogOpen = false" variant="outline" :disabled="isDeleting">
                            {{ t('agents.sources.common.cancel') }}
                        </Button>
                        <Button @click="deleteEntry" variant="destructive" :disabled="isDeleting">
                            <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                            <Trash2 v-else class="me-2 h-4 w-4" />
                            {{ t('agents.sources.common.delete') }}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
