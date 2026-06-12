<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AttachTextDialog from '@/components/ui/AttachTextDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import IngestionProgressBar from '@/components/ui/IngestionProgressBar.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { useForm } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import axios from 'axios';
import { AlertCircle, CheckCircle, ChevronDown, Edit3, FileText, Link2, LoaderCircle, Plus, Save, Trash2, Type, Unlink } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

interface TextEntry {
    id: number;
    excerpt: string;
    content?: string;
    status: 'pending' | 'ingesting' | 'ingested' | 'failed';
    created_at: string;
    updated_at: string;
    word_count: number;
    progress?: number;
    agents_count?: number;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { hasPermission } = useTeamPermissions();

// Check if user can delete sources
const canDeleteSources = computed(() => hasPermission('agent.sources.delete'));

// WebSocket event listener for text ingestion status updates
useEcho(`agents.${props.agent.external_id}.ingestion.text`, '.TextIngestionStarted', (e) => {
    handleIngestionUpdate(e);
});

// WebSocket event listener for text ingestion progress updates
useEcho(`agents.${props.agent.external_id}.texts.progress`, '.TextIngestionProgressed', (e) => {
    handleTextIngestionProgress(e);
});

// State
const textEntries = ref<TextEntry[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const showCreateForm = ref(false);
const editingEntry = ref<TextEntry | null>(null);

// Delete confirmation dialog
const deleteDialogOpen = ref(false);
const entryToDelete = ref<TextEntry | null>(null);
const isUnlinking = ref(false);
const isDeleting = ref(false);

// Attach text dialog
const attachTextDialogOpen = ref(false);

// Form for creating/editing text entries
const form = useForm({
    content: '',
});

// Computed
const wordCount = computed(() => {
    if (!form.content) return 0;
    return form.content
        .trim()
        .split(/\s+/)
        .filter((word) => word.length > 0).length;
});

const charCount = computed(() => {
    return form.content ? form.content.length : 0;
});

// Methods
const openCreateForm = () => {
    showCreateForm.value = true;
    editingEntry.value = null;
    form.reset();
};

const openEditForm = async (entry: TextEntry) => {
    try {
        isLoading.value = true;
        const response = await axios.get(
            route('agents.sources.text.show', {
                agent: props.agent.external_id,
                id: entry.id,
            }),
        );

        editingEntry.value = entry;
        form.content = response.data.content;
        showCreateForm.value = true;
    } catch (error) {
        console.error('Failed to fetch text entry:', error);
        toast.error(t('agents.sources.text.load_failed'), {
            description: t('agents.sources.text.load_failed_description'),
        });
    } finally {
        isLoading.value = false;
    }
};

const cancelForm = () => {
    showCreateForm.value = false;
    editingEntry.value = null;
    form.reset();
    form.clearErrors();
};

const saveTextEntry = () => {
    isSubmitting.value = true;

    const onSuccess = () => {
        showCreateForm.value = false;
        editingEntry.value = null;
        form.reset();
        fetchTextEntries();
        // Refresh usage data from server after save
        fetchUsageData();
        isSubmitting.value = false;
    };

    const onError = (error: any) => {
        console.error('Failed to save text entry:', error);
        isSubmitting.value = false;
    };

    if (editingEntry.value) {
        // Update existing entry
        form.patch(
            route('agents.sources.text.update', {
                agent: props.agent.external_id,
                id: editingEntry.value.id,
            }),
            {
                onSuccess,
                onError,
            },
        );
    } else {
        // Create new entry
        form.post(route('agents.sources.text.store', { agent: props.agent.external_id }), {
            onSuccess,
            onError,
        });
    }
};

const confirmDelete = (entry: TextEntry) => {
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

const isTextShared = (entry: TextEntry): boolean => {
    return (entry.agents_count ?? 0) > 1;
};

const unlinkTextEntry = async () => {
    if (!entryToDelete.value || isUnlinking.value) return;

    const textToUnlink = entryToDelete.value;
    isUnlinking.value = true;

    try {
        await axios.post(
            route('agents.sources.text.unlink', {
                agent: props.agent.external_id,
                id: textToUnlink.id,
            }),
        );

        toast.success(t('agents.sources.text.unlinked'));

        deleteDialogOpen.value = false;
        entryToDelete.value = null;

        await fetchTextEntries();
        await fetchUsageData();
    } catch (error: any) {
        console.error('Error unlinking text:', error);
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(t('agents.sources.text.unlink_failed', { error: errorMessage }));
    } finally {
        isUnlinking.value = false;
    }
};

const deleteTextEntry = async () => {
    if (!entryToDelete.value || isDeleting.value) return;

    const textToDelete = entryToDelete.value;
    isDeleting.value = true;

    try {
        await axios.delete(
            route('agents.sources.text.destroy', {
                agent: props.agent.external_id,
                id: textToDelete.id,
            }),
        );

        toast.success(t('agents.sources.text.deleted'));

        deleteDialogOpen.value = false;
        entryToDelete.value = null;

        await fetchTextEntries();
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to delete text entry:', error);
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(t('agents.sources.text.delete_failed', { error: errorMessage }));
    } finally {
        isDeleting.value = false;
    }
};

const fetchTextEntries = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('agents.sources.text.index', { agent: props.agent.external_id }));
        textEntries.value = response.data;
    } catch (error) {
        console.error('Failed to fetch text entries:', error);
    } finally {
        isLoading.value = false;
    }
};

// Fetch usage data from server
const fetchUsageData = async () => {
    try {
        await axios.get(route('usage'));
        // Usage data is fetched from server, not stored locally in this component
    } catch (error) {
        console.error('Error fetching usage data:', error);
    }
};

const openAttachTextDialog = () => {
    attachTextDialogOpen.value = true;
};

const handleTextAttached = () => {
    fetchTextEntries();
};

const handleIngestionUpdate = async (event: any) => {
    // The event structure: { status: "pending" | "ingesting" | "ingested" | "failed", id: number }
    const { status, id: entry_id } = event;

    // Find the entry by ID
    const entryIndex = textEntries.value.findIndex((entry) => entry.id === entry_id);

    if (entryIndex === -1) {
        // If entry not found, refresh the list to ensure we have the latest data
        await fetchTextEntries();
        return;
    }

    const entry = textEntries.value[entryIndex];

    // Update the entry status
    textEntries.value[entryIndex] = {
        ...entry,
        status: status,
    };

    // Show toast notifications based on status
    switch (status) {
        case 'ingesting':
            toast.info(t('agents.sources.text.processing_started'), {
                description: t('agents.sources.text.processing_started_description'),
            });
            break;
        case 'ingested':
            toast.success(t('agents.sources.text.processing_success'), {
                description: t('agents.sources.text.processing_success_description'),
            });
            break;
        case 'failed':
            toast.error(t('agents.sources.text.processing_failed'), {
                description: t('agents.sources.text.processing_failed_description'),
            });
            break;
    }
};

// Handle text ingestion progress updates from WebSocket
const handleTextIngestionProgress = (event: any) => {
    const { text_id, progress } = event;

    if (!text_id || progress === undefined) {
        return;
    }

    // Find the text entry and update its progress
    const entryIndex = textEntries.value.findIndex((entry) => entry.id === text_id);

    if (entryIndex !== -1) {
        // Create a new array with the updated entry for reactivity
        const updatedEntries = [...textEntries.value];

        updatedEntries[entryIndex] = {
            ...updatedEntries[entryIndex],
            progress: progress,
            status: progress >= 100 ? 'ingested' : 'ingesting',
        };
        textEntries.value = updatedEntries;

        // If progress is 100%, update the status to 'ingested' immediately
        if (progress >= 100) {
            // Refresh entries to get the complete updated data
            setTimeout(() => {
                fetchTextEntries();
            }, 1000); // Small delay to ensure backend has updated
        }
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'ingested':
            return CheckCircle;
        case 'pending':
        case 'ingesting':
            return LoaderCircle;
        case 'failed':
            return AlertCircle;
        default:
            return LoaderCircle;
    }
};

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'ingested':
            return 'text-green-600';
        case 'pending':
        case 'ingesting':
            return 'text-blue-600';
        case 'failed':
            return 'text-red-600';
        default:
            return 'text-gray-600';
    }
};

const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'ingested':
            return t('agents.sources.text.status.trained');
        case 'pending':
            return t('agents.sources.text.status.pending');
        case 'ingesting':
            return t('agents.sources.text.status.training');
        case 'failed':
            return t('agents.sources.text.status.failed');
        default:
            return t('agents.sources.text.status.unknown');
    }
};

// Dates follow the user's chosen format (Settings → Date & time), with a 24h time.
const { formatDateTime: formatDate } = useDateFormat();

// Load text entries on component mount
fetchTextEntries();
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Type class="h-5 w-5" />
                                {{ t('agents.sources.text.title') }}
                            </CardTitle>
                            <CardDescription>
                                {{ t('agents.sources.text.description') }}
                            </CardDescription>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button class="flex items-center gap-2">
                                    <Plus class="h-4 w-4" />
                                    {{ t('agents.sources.text.add_text') }}
                                    <ChevronDown class="ml-1 h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-64">
                                <DropdownMenuGroup>
                                    <DropdownMenuItem @click="openCreateForm" class="cursor-pointer">
                                        <Plus class="mr-2 h-4 w-4" />
                                        {{ t('agents.sources.text.create_new') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openAttachTextDialog" class="cursor-pointer">
                                        <Link2 class="mr-2 h-4 w-4" />
                                        {{ t('agents.sources.text.already_trained') }}
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </CardHeader>
            </Card>

            <!-- Create/Edit Form -->
            <Card v-if="showCreateForm">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Edit3 class="h-5 w-5" />
                        {{ editingEntry ? t('agents.sources.text.edit_entry') : t('agents.sources.text.create_entry') }}
                    </CardTitle>
                    <CardDescription>
                        {{ editingEntry ? t('agents.sources.text.edit_entry_description') : t('agents.sources.text.create_entry_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Content Textarea -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="content">{{ t('agents.sources.text.content_label') }}</Label>
                            <div class="text-muted-foreground space-x-4 text-sm">
                                <span>{{ t('agents.sources.text.word_count', { count: wordCount }) }}</span>
                                <span>{{ t('agents.sources.text.char_count', { count: charCount }) }}</span>
                            </div>
                        </div>
                        <Textarea
                            id="content"
                            v-model="form.content"
                            :disabled="isSubmitting"
                            :placeholder="t('agents.sources.text.content_placeholder')"
                            rows="12"
                            :class="{ 'border-red-500': form.errors.content }"
                            class="resize-none"
                        />
                        <InputError :message="form.errors.content" />
                        <p v-if="!form.errors.content" class="text-muted-foreground text-sm">
                            {{ t('agents.sources.text.content_hint') }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-between border-t pt-4">
                        <Button variant="outline" @click="cancelForm" :disabled="isSubmitting"> {{ t('agents.sources.common.cancel') }} </Button>
                        <Button @click="saveTextEntry" :disabled="isSubmitting" class="flex items-center gap-2">
                            <LoaderCircle v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            {{ isSubmitting ? t('agents.sources.text.saving') : editingEntry ? t('agents.sources.text.update_entry') : t('agents.sources.text.save_entry') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Text Entries List -->
            <Card v-if="!isLoading && textEntries.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        {{ t('agents.sources.text.entries_title', { count: textEntries.length }) }}
                    </CardTitle>
                    <CardDescription> {{ t('agents.sources.text.entries_description') }} </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="entry in textEntries" :key="entry.id" class="hover:bg-muted/50 rounded-lg border p-4 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-start gap-2">
                                    <p class="text-muted-foreground line-clamp-2 flex-1 text-sm">
                                        {{ entry.excerpt }}
                                    </p>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <div class="flex items-center">
                                                    <component
                                                        :is="getStatusIcon(entry.status)"
                                                        :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(entry.status)} ${entry.status === 'pending' || entry.status === 'ingesting' ? 'animate-spin' : ''}`"
                                                    />
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ getStatusLabel(entry.status) }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <div class="text-muted-foreground flex items-center gap-4 text-xs">
                                    <span>{{ formatDate(entry.created_at) }}</span>
                                </div>
                                <!-- Progress bar for processing text entries -->
                                <IngestionProgressBar v-if="entry.status === 'ingesting'" :progress="entry.progress ?? 0" />
                            </div>
                            <div class="ml-4 flex items-center gap-2">
                                <TooltipProvider v-if="entry.status !== 'pending' && entry.status !== 'ingesting'">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button variant="outline" size="sm" @click="openEditForm(entry)" class="h-8 w-8 p-0">
                                                <Edit3 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ t('agents.sources.text.edit_tooltip') }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="confirmDelete(entry)"
                                                class="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <div class="text-xs">
                                                <p v-if="isTextShared(entry)">
                                                    {{ t('agents.sources.common.remove_from_agent') }}<br />
                                                    <span class="text-muted-foreground">{{ t('agents.sources.common.used_in_agents', { count: entry.agents_count }) }}</span>
                                                </p>
                                                <p v-else>{{ t('agents.sources.common.delete_from_knowledge_base') }}</p>
                                            </div>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <Card v-else-if="!isLoading && textEntries.length === 0 && !showCreateForm">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <Type class="text-muted-foreground mb-4 h-16 w-16" />
                    <h3 class="mb-2 text-lg font-medium">{{ t('agents.sources.text.empty_title') }}</h3>
                    <p class="text-muted-foreground mb-6 max-w-md text-center">
                        {{ t('agents.sources.text.empty_description') }}
                    </p>
                    <Button @click="openCreateForm" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" />
                        {{ t('agents.sources.text.create_first') }}
                    </Button>
                </CardContent>
            </Card>

            <!-- Loading State -->
            <Card v-if="isLoading">
                <CardContent class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-primary h-8 w-8 animate-spin" />
                    <span class="ms-2 text-lg">{{ t('agents.sources.text.loading') }}</span>
                </CardContent>
            </Card>

            <!-- Delete/Unlink Confirmation Dialog -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent v-if="entryToDelete" class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ entryToDelete.excerpt.substring(0, 100) }}...</DialogTitle>
                        <DialogDescription>
                            <span v-if="isTextShared(entryToDelete)">
                                {{ t('agents.sources.text.delete_shared_prefix') }}
                                <strong>{{ t('agents.sources.common.agents_count', { count: entryToDelete.agents_count }) }}</strong>
                                {{ t('agents.sources.text.delete_shared_suffix') }}
                            </span>
                            <span v-else>
                                {{ t('agents.sources.text.delete_description') }}
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <Button variant="outline" @click="deleteDialogOpen = false" :disabled="isUnlinking || isDeleting"> {{ t('agents.sources.common.cancel') }} </Button>
                        <Button
                            v-if="isTextShared(entryToDelete)"
                            variant="secondary"
                            @click="unlinkTextEntry"
                            :disabled="isUnlinking || isDeleting"
                            class="bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
                        >
                            <LoaderCircle v-if="isUnlinking" class="me-2 h-4 w-4 animate-spin" />
                            <Unlink v-else class="me-2 h-4 w-4" />
                            {{ isUnlinking ? t('agents.sources.common.unlinking') : t('agents.sources.common.unlink') }}
                        </Button>
                        <Button variant="destructive" @click="deleteTextEntry" :disabled="isUnlinking || isDeleting">
                            <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                            <Trash2 v-else class="me-2 h-4 w-4" />
                            <span v-if="isDeleting">{{ t('agents.sources.common.deleting') }}</span>
                            <span v-else>{{ isTextShared(entryToDelete) ? t('agents.sources.common.delete_permanently') : t('agents.sources.common.delete') }}</span>
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Attach Text Dialog -->
            <AttachTextDialog v-model:open="attachTextDialogOpen" :agent-id="agent.external_id" @textAttached="handleTextAttached" />
        </div>
    </div>
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
