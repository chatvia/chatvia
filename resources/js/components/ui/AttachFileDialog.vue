<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { LoaderCircle, FileText, Search, CheckCircle, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface Props {
    open: boolean;
    agentId: string; // Accept external_id (string) or id (number)
    fileType?: string; // 'pdf', 'word', 'audio', 'video'
}

interface AvailableFile {
    id: number;
    filename: string;
    filetype: string;
    length: string;
    agents?: Array<{
        id: number;
        external_id: string;
        name: string;
    }>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'fileAttached': [];
}>();

// State
const isLoading = ref(false);
const availableFiles = ref<AvailableFile[]>([]);
const searchQuery = ref('');
const addedFileIds = ref<Set<number>>(new Set());
const attachingFileIds = ref<Set<number>>(new Set());

// Computed
const filteredFiles = computed(() => {
    if (!searchQuery.value.trim()) {
        return availableFiles.value;
    }

    const query = searchQuery.value.toLowerCase().trim();
    return availableFiles.value.filter(file =>
        file.filename.toLowerCase().includes(query)
    );
});

const isFileAdded = (fileId: number) => {
    return addedFileIds.value.has(fileId);
};

const isFileAttaching = (fileId: number) => {
    return attachingFileIds.value.has(fileId);
};

// Methods
const fetchAvailableFiles = async () => {
    isLoading.value = true;
    try {
        const params: any = {};
        if (props.fileType) {
            params.type = props.fileType;
        }

        const response = await axios.get(route('agents.sources.list', { agent: props.agentId }), { params });
        availableFiles.value = response.data;
    } catch (error: any) {
        console.error('Error fetching available files:', error);
        toast.error('Failed to load available files.');
    } finally {
        isLoading.value = false;
    }
};

const attachFile = async (file: AvailableFile) => {
    if (isFileAdded(file.id) || isFileAttaching(file.id)) return;

    attachingFileIds.value.add(file.id);

    try {
        await axios.post(route('agents.sources.files.link', {
            agent: props.agentId,
            fileId: file.id
        }), {
            file_id: file.id
        });

        addedFileIds.value.add(file.id);
        toast.success(`${file.filename} added to agent successfully!`);
        emit('fileAttached');
    } catch (error: any) {
        console.error('Error attaching file:', error);
        const errorMessage = error.response?.data?.message || 'Failed to add file to agent.';
        toast.error(errorMessage);
    } finally {
        attachingFileIds.value.delete(file.id);
    }
};

const closeDialog = () => {
    emit('update:open', false);
    searchQuery.value = '';
};

const formatFileType = (fileType: string): string => {
    const typeMap: Record<string, string> = {
        'pdf': 'PDF',
        'word': 'Word',
        'audio': 'Audio',
        'video': 'Video',
    };
    return typeMap[fileType] || fileType.toUpperCase();
};

const formatFileLength = (file: AvailableFile): string => {
    if (!file.length) return '';

    // For audio and video files, use minutes
    if (file.filetype === 'audio' || file.filetype === 'video') {
        return `${file.length} minute${file.length !== '1' ? 's' : ''}`;
    }

    // For other files, use pages
    return `${file.length} page${file.length !== '1' ? 's' : ''}`;
};

// Watch for dialog open state
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        fetchAvailableFiles();
        addedFileIds.value.clear();
        attachingFileIds.value.clear();
    } else {
        searchQuery.value = '';
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-3xl max-h-[85vh] overflow-hidden">
            <DialogHeader class="pr-6">
                <DialogTitle class="flex items-center gap-2">
                    <Link2 class="h-5 w-5 text-blue-600 flex-shrink-0" />
                    <span class="truncate">Choose from Already Trained Files</span>
                </DialogTitle>
                <DialogDescription class="pr-8">
                    Select a file from your library that has already been trained to add to this agent.
                    <span v-if="fileType"> Showing {{ formatFileType(fileType) }} files only.</span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 overflow-hidden">
                <!-- Search -->
                <div class="relative w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none z-10" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search files..."
                        class="pl-9 w-full"
                        :disabled="isLoading"
                    />
                </div>

                <!-- Files List -->
                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <LoaderCircle class="h-6 w-6 animate-spin text-blue-600 mr-2" />
                    <span class="text-muted-foreground">Loading files...</span>
                </div>

                <div v-else-if="availableFiles.length === 0" class="text-center py-8 text-muted-foreground">
                    <FileText class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p>No trained files available.</p>
                    <p class="text-sm mt-1">Files must be fully trained before they can be shared across agents.</p>
                </div>

                <div v-else-if="filteredFiles.length === 0" class="text-center py-8 text-muted-foreground">
                    <Search class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p>No files match your search.</p>
                </div>

                <ScrollArea v-else class="h-[450px]">
                    <div class="space-y-3 pr-4">
                        <div
                            v-for="file in filteredFiles"
                            :key="file.id"
                            class="w-full flex items-start gap-3 border rounded-lg p-4 transition-colors"
                            :class="{
                                'border-green-500 bg-green-50 dark:bg-green-950/20': isFileAdded(file.id),
                                'border-border': !isFileAdded(file.id)
                            }"
                        >
                            <div class="p-2 rounded-lg bg-accent/50 flex-shrink-0">
                                <FileText class="h-5 w-5 text-foreground" />
                            </div>

                            <div class="min-w-0 flex-1 overflow-hidden">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <h4 class="font-semibold text-sm truncate mb-1 cursor-help">
                                                {{ file.filename }}
                                            </h4>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" class="max-w-sm">
                                            <p class="break-words">{{ file.filename }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <div class="flex items-center gap-2 text-xs text-muted-foreground flex-wrap">
                                    <Badge variant="secondary" class="text-xs font-medium flex-shrink-0">
                                        {{ formatFileType(file.filetype) }}
                                    </Badge>
                                    <span v-if="formatFileLength(file)" class="flex items-center gap-1 flex-shrink-0">
                                        <span>•</span>
                                        {{ formatFileLength(file) }}
                                    </span>
                                    <span v-if="file.agents && file.agents.length > 0" class="flex items-center gap-1 flex-shrink-0">
                                        <span>•</span>
                                        <Users class="h-3 w-3" />
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="hover:text-foreground cursor-help whitespace-nowrap">
                                                        {{ file.agents.length }} agent{{ file.agents.length !== 1 ? 's' : '' }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <div class="space-y-1">
                                                        <p class="font-semibold text-xs mb-1">Used in:</p>
                                                        <ul class="list-disc list-inside space-y-0.5">
                                                            <li v-for="agent in file.agents" :key="agent.external_id" class="text-xs">
                                                                {{ agent.name }}
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </span>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <Button
                                    v-if="!isFileAdded(file.id)"
                                    size="sm"
                                    @click="attachFile(file)"
                                    :disabled="isFileAttaching(file.id)"
                                    class="h-8 whitespace-nowrap"
                                >
                                    <LoaderCircle v-if="isFileAttaching(file.id)" class="h-4 w-4 animate-spin" />
                                    <template v-else>
                                        <Plus class="h-4 w-4 mr-1" />
                                        Add
                                    </template>
                                </Button>
                                <div v-else class="flex items-center gap-1.5 text-green-600 dark:text-green-500 font-medium text-sm whitespace-nowrap">
                                    <Check class="h-4 w-4 flex-shrink-0" />
                                    <span>Added</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </div>
        </DialogContent>
    </Dialog>
</template>

