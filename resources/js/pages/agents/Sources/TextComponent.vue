<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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
    Edit3
} from 'lucide-vue-next';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';

interface Props {
    agent: any;
}

interface TextEntry {
    id: number;
    excerpt: string;
    status: 'draft' | 'saved' | 'processing' | 'trained' | 'failed';
    created_at: string;
    updated_at: string;
    word_count: number;
}

const props = defineProps<Props>();

// State
const textEntries = ref<TextEntry[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const showCreateForm = ref(false);
const editingEntry = ref<TextEntry | null>(null);

// Delete confirmation dialog
const deleteDialogOpen = ref(false);
const entryToDelete = ref<TextEntry | null>(null);

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

const openEditForm = (entry: TextEntry) => {
    showCreateForm.value = true;
    editingEntry.value = entry;
    // form.content = entry.content;
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
            textEntry: editingEntry.value.id
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

const deleteTextEntry = async () => {
    if (!entryToDelete.value) return;

    try {
        await axios.delete(route('agents.sources.text.destroy', {
            agent: props.agent.id,
            id: entryToDelete.value.id
        }));

        fetchTextEntries();
    } catch (error) {
        console.error('Failed to delete text entry:', error);
    } finally {
        deleteDialogOpen.value = false;
        entryToDelete.value = null;
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

const getStatusBadge = (status: string) => {
    const statusConfig: Record<string, { variant: 'default' | 'destructive' | 'outline' | 'secondary'; label: string; icon: any }> = {
        draft: { variant: 'secondary', label: 'Draft', icon: Edit3 },
        saved: { variant: 'outline', label: 'Saved', icon: FileText },
        processing: { variant: 'default', label: 'Processing', icon: LoaderCircle },
        trained: { variant: 'default', label: 'Trained', icon: CheckCircle },
        failed: { variant: 'destructive', label: 'Failed', icon: AlertCircle }
    };

    return statusConfig[status] || statusConfig.draft;
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
    <div class="space-y-6">
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
                    <Button @click="openCreateForm" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" />
                        Add Text
                    </Button>
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
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="font-medium text-sm truncate">
                                    {{ entry.excerpt }}
                                </h3>
                                <Badge :variant="getStatusBadge(entry.status).variant" class="flex items-center gap-1">
                                    <component :is="getStatusBadge(entry.status).icon" class="h-3 w-3" />
                                    {{ getStatusBadge(entry.status).label }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground line-clamp-2 mb-2">
                                {{ entry.content.substring(0, 150) }}{{ entry.content.length > 150 ? '...' : '' }}
                            </p>
                            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                <span>{{ entry.word_count }} words</span>
                                <span>Created {{ formatDate(entry.created_at) }}</span>
                                <span v-if="entry.updated_at !== entry.created_at">
                                    Updated {{ formatDate(entry.updated_at) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <TooltipProvider>
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
                                        <p>Delete entry</p>
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

        <!-- Delete Confirmation Dialog -->
        <DeleteConfirmationDialog
            v-model:open="deleteDialogOpen"
            title="Delete text entry?"
            description="This will permanently remove this text entry from your agent's knowledge base. This action cannot be undone."
            confirm-text="Yes, delete entry"
            cancel-text="Cancel"
            @confirm="deleteTextEntry"
        />
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
