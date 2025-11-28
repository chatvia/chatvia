<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useEcho } from "@laravel/echo-vue";
import { toast } from 'vue-sonner';
import axios from "axios";
import {
    Type,
    Plus,
    Save,
    Trash2,
    LoaderCircle,
    AlertCircle,
    CheckCircle,
    FileText,
    Edit3,
    Link2,
    ChevronDown,
    Unlink
} from 'lucide-vue-next';
import IngestionProgressBar from '@/components/ui/IngestionProgressBar.vue';
import AttachTextDialog from '@/components/ui/AttachTextDialog.vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
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

// WebSocket event listener for text ingestion status updates
useEcho(
    `agents.${props.agent.id}.ingestion.text`,
    "TextIngestionEvent",
    (e) => {
        handleIngestionUpdate(e);
    },
);

// WebSocket event listener for text ingestion progress updates
useEcho(
    `agents.${props.agent.id}.texts.progress`,
    "TextIngestionProgressEvent",
    (e) => {
        handleTextIngestionProgress(e);
    },
);

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
    content: ''
});

// Computed
const wordCount = computed(() => {
    if (!form.content) return 0;
    return form.content.trim().split(/\s+/).filter(word => word.length > 0).length;
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
        const response = await axios.get(route('agents.sources.text.show', {
            agent: props.agent.id,
            id: entry.id
        }));

        editingEntry.value = entry;
        form.content = response.data.content;
        showCreateForm.value = true;
    } catch (error) {
        console.error('Failed to fetch text entry:', error);
        toast.error('Failed to load text entry', {
            description: 'There was an error loading the text content for editing.',
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
        form.patch(route('agents.sources.text.update', {
            agent: props.agent.id,
            id: editingEntry.value.id
        }), {
            onSuccess,
            onError
        });
    } else {
        // Create new entry
        form.post(route('agents.sources.text.store', { agent: props.agent.id }), {
            onSuccess,
            onError
        });
    }
};

const confirmDelete = (entry: TextEntry) => {
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
        await axios.post(route('agents.sources.text.unlink', {
            agent: props.agent.id,
            id: textToUnlink.id
        }));

        toast.success('Text unlinked from agent successfully!');
        
        deleteDialogOpen.value = false;
        entryToDelete.value = null;

        await fetchTextEntries();
        await fetchUsageData();
    } catch (error: any) {
        console.error('Error unlinking text:', error);
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(`Failed to unlink text: ${errorMessage}`);
    } finally {
        isUnlinking.value = false;
    }
};

const deleteTextEntry = async () => {
    if (!entryToDelete.value || isDeleting.value) return;

    const textToDelete = entryToDelete.value;
    isDeleting.value = true;

    try {
        await axios.delete(route('agents.sources.text.destroy', {
            agent: props.agent.id,
            id: textToDelete.id
        }));

        toast.success('Text deleted successfully!');
        
        deleteDialogOpen.value = false;
        entryToDelete.value = null;

        await fetchTextEntries();
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to delete text entry:', error);
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(`Failed to delete text: ${errorMessage}`);
    } finally {
        isDeleting.value = false;
    }
};

const fetchTextEntries = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('agents.sources.text.index', { agent: props.agent.id }));
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
    const entryIndex = textEntries.value.findIndex(entry => entry.id === entry_id);

    if (entryIndex === -1) {
        // If entry not found, refresh the list to ensure we have the latest data
        await fetchTextEntries();
        return;
    }

    const entry = textEntries.value[entryIndex];

    // Update the entry status
    textEntries.value[entryIndex] = {
        ...entry,
        status: status
    };

    // Show toast notifications based on status
    switch (status) {
        case 'ingesting':
            toast.info('Processing started', {
                description: 'Your text entry is being processed...',
            });
            break;
        case 'ingested':
            toast.success('Success!', {
                description: 'Text entry has been successfully added to your knowledge base.',
            });
            break;
        case 'failed':
            toast.error('Processing failed', {
                description: 'There was an error processing your text entry.',
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
    const entryIndex = textEntries.value.findIndex(entry => entry.id === text_id);
    
    if (entryIndex !== -1) {
        // Create a new array with the updated entry for reactivity
        const updatedEntries = [...textEntries.value];

        updatedEntries[entryIndex] = {
            ...updatedEntries[entryIndex],
            progress: progress,
            status: progress >= 100 ? 'ingested' : 'ingesting'
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
            return 'Trained';
        case 'pending':
            return 'Pending';
        case 'ingesting':
            return 'Training';
        case 'failed':
            return 'Failed';
        default:
            return 'Unknown';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

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
                            Text Knowledge
                        </CardTitle>
                        <CardDescription>
                            Add custom text content directly to your agent's knowledge base. Perfect for FAQs, policies, and structured information.
                        </CardDescription>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button class="flex items-center gap-2">
                                <Plus class="h-4 w-4" />
                                Add Text
                                <ChevronDown class="h-4 w-4 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-64">
                            <DropdownMenuGroup>
                                <DropdownMenuItem @click="openCreateForm" class="cursor-pointer">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create New Text
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openAttachTextDialog" class="cursor-pointer">
                                    <Link2 class="mr-2 h-4 w-4" />
                                    Already Trained Texts
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
                    {{ editingEntry ? 'Edit Text Entry' : 'Create New Text Entry' }}
                </CardTitle>
                <CardDescription>
                    {{ editingEntry ? 'Update your text content' : 'Add structured text content to your knowledge base' }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Content Textarea -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="content">Content *</Label>
                        <div class="text-sm text-muted-foreground space-x-4">
                            <span>{{ wordCount }} words</span>
                            <span>{{ charCount }} characters</span>
                        </div>
                    </div>
                    <Textarea
                        id="content"
                        v-model="form.content"
                        :disabled="isSubmitting"
                        placeholder="Enter your text content here. This can include FAQs, policies, instructions, or any structured information you want your agent to know about..."
                        rows="12"
                        :class="{ 'border-red-500': form.errors.content }"
                        class="resize-none"
                    />
                    <InputError :message="form.errors.content" />
                    <p v-if="!form.errors.content" class="text-sm text-muted-foreground">
                        Minimum 250 words required. Use clear, structured text for best results.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-4 border-t">
                    <Button variant="outline" @click="cancelForm" :disabled="isSubmitting">
                        Cancel
                    </Button>
                    <Button
                        @click="saveTextEntry"
                        :disabled="isSubmitting"
                        class="flex items-center gap-2"
                    >
                        <LoaderCircle v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        {{ isSubmitting ? 'Saving...' : (editingEntry ? 'Update Entry' : 'Save Entry') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Text Entries List -->
        <Card v-if="!isLoading && textEntries.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    Your Text Entries ({{ textEntries.length }})
                </CardTitle>
                <CardDescription>
                    Manage your custom text content
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-for="entry in textEntries"
                    :key="entry.id"
                    class="p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-2 mb-2">
                                <p class="text-sm text-muted-foreground line-clamp-2 flex-1">
                                    {{ entry.excerpt }}
                                </p>
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div class="flex items-center">
                                                <component
                                                    :is="getStatusIcon(entry.status)"
                                                    :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(entry.status)} ${(entry.status === 'pending' || entry.status === 'ingesting') ? 'animate-spin' : ''}`"
                                                />
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ getStatusLabel(entry.status) }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                <span>{{ formatDate(entry.created_at) }}</span>
                            </div>
                            <!-- Progress bar for processing text entries -->
                            <IngestionProgressBar
                                v-if="entry.status === 'ingesting'"
                                :progress="entry.progress ?? 0"
                            />
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <TooltipProvider v-if="entry.status !== 'pending' && entry.status !== 'ingesting'">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openEditForm(entry)"
                                            class="h-8 w-8 p-0"
                                        >
                                            <Edit3 class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Edit entry</p>
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
                                            class="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <div class="text-xs">
                                            <p v-if="isTextShared(entry)">
                                                Remove from agent<br>
                                                <span class="text-muted-foreground">(Used in {{ entry.agents_count }} agents)</span>
                                            </p>
                                            <p v-else>Delete from knowledge base</p>
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
                <Type class="h-16 w-16 text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium mb-2">No text entries yet</h3>
                <p class="text-muted-foreground text-center max-w-md mb-6">
                    Start by adding your first text entry. You can include FAQs, policies, instructions, or any structured information for your agent.
                </p>
                <Button @click="openCreateForm" class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create Your First Text Entry
                </Button>
            </CardContent>
        </Card>

        <!-- Loading State -->
        <Card v-if="isLoading">
            <CardContent class="flex items-center justify-center py-12">
                <LoaderCircle class="h-8 w-8 animate-spin text-primary" />
                <span class="ml-2 text-lg">Loading text entries...</span>
            </CardContent>
        </Card>

        <!-- Delete/Unlink Confirmation Dialog -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent v-if="entryToDelete" class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ entryToDelete.excerpt.substring(0, 100) }}...</DialogTitle>
                    <DialogDescription>
                        <span v-if="isTextShared(entryToDelete)">
                            This text is used in <strong>{{ entryToDelete.agents_count }} agents</strong>. 
                            You can unlink it from this agent or permanently delete it from all agents.
                        </span>
                        <span v-else>
                            This will permanently remove this text entry from your agent's knowledge base. This action cannot be undone.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <Button 
                        variant="outline" 
                        @click="deleteDialogOpen = false"
                        :disabled="isUnlinking || isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button 
                        v-if="isTextShared(entryToDelete)" 
                        variant="secondary" 
                        @click="unlinkTextEntry"
                        :disabled="isUnlinking || isDeleting"
                        class="bg-orange-500 hover:bg-orange-600 text-white disabled:opacity-50"
                    >
                        <LoaderCircle v-if="isUnlinking" class="h-4 w-4 mr-2 animate-spin" />
                        <Unlink v-else class="h-4 w-4 mr-2" />
                        {{ isUnlinking ? 'Unlinking...' : 'Unlink' }}
                    </Button>
                    <Button 
                        variant="destructive" 
                        @click="deleteTextEntry"
                        :disabled="isUnlinking || isDeleting"
                    >
                        <LoaderCircle v-if="isDeleting" class="h-4 w-4 mr-2 animate-spin" />
                        <Trash2 v-else class="h-4 w-4 mr-2" />
                        <span v-if="isDeleting">Deleting...</span>
                        <span v-else>{{ isTextShared(entryToDelete) ? 'Delete permanently' : 'Delete' }}</span>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Attach Text Dialog -->
        <AttachTextDialog
            v-model:open="attachTextDialogOpen"
            :agent-id="agent.id"
            @textAttached="handleTextAttached"
        />
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
