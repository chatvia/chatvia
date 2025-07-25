<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Database, FileText, Trash2, LoaderCircle, File, AlertCircle, CheckCircle, Play, Zap } from 'lucide-vue-next';
import AgentLayout from '@/layouts/AgentLayout.vue';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import FileUploader from '@/components/FileUploader.vue';
import type { UploadingFileData as BaseUploadingFileData, FileUploaderConfig } from '@/composables/useFileUploader';

import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

const page = usePage();

interface Props {
    agent: any
    // Removed quota and usage props - will fetch from API
}

interface KnowledgeFile {
    id: number;
    name: string;
    size_in_kb: number;
    mime_type: string;
    status: string;
    created_at: string;
    number_of_pages?: number;
    ingestion_progress?: number;
}

interface UploadedFile {
    id: number;
    name: string;
    size: number;
    mime_type: string;
    status: string;
    created_at: string;
    pages?: number;
    encrypted?: boolean;
}

// Extend the base interface with Knowledge.vue specific properties
interface UploadingFileData extends BaseUploadingFileData {
    isUploaded?: boolean;
    uploadedFile?: UploadedFile;
}

// Usage data interface
interface UsageData {
    pages: {
        limit: number;
        used: number;
    };
}

// Constants
const maxFilesToUpload = 20;

const props = defineProps<Props>();

// Core state
const uploadingFiles = ref<UploadingFileData[]>([]);
const fileUploaderRef = ref<InstanceType<typeof FileUploader> | null>(null);
const knowledgeFiles = ref<KnowledgeFile[]>([]);
const uploadedFiles = ref<UploadedFile[]>([]);

// Usage data state
const usageData = ref<UsageData>({
    pages: {
        limit: 0,
        used: 0
    }
});
const isLoadingUsage = ref(false);

// File uploader configuration for Knowledge page
const fileUploaderConfig: FileUploaderConfig = {
    maxFiles: maxFilesToUpload,
    acceptedTypes: '.pdf,.doc,.docx,.txt,.md',
    analyzeEndpoint: (file: File) => route('agents.knowledge.files.upload', { agent: props.agent.id }),
    onFileAnalyzed: (file: BaseUploadingFileData, data: any) => {
        // For Knowledge.vue, the upload endpoint uploads the file
        // Remove the file from FileUploader since it's now uploaded and will show in "Ready to Train"
        if (fileUploaderRef.value) {
            fileUploaderRef.value.removeFileById(file.id);
        }
        // Refresh the knowledge files list to show in "Ready to Train"
        fetchKnowledgeFiles();
    },
    onFileError: (file: BaseUploadingFileData, error: string) => {
        console.error('File upload error:', error);
    }
};
const isLoadingFiles = ref(false);
const isInitialLoad = ref(true);
const isProcessingFiles = ref(false);

// Auto-refresh for processing files
const refreshInterval = ref<number | null>(null);

// Selection state - using Set for better performance
const selectedFileIds = ref<Set<string>>(new Set());

// Dialog states
const deleteDialogOpen = ref(false);
const fileToDelete = ref<KnowledgeFile | null>(null);
const deleteUploadingFileDialogOpen = ref(false);
const uploadingFileToDelete = ref<UploadingFileData | null>(null);
const bulkDeleteDialogOpen = ref(false);
const clearAllDialogOpen = ref(false);

// Filter and sort state
const searchQuery = ref('');
const sortBy = ref<'name' | 'size' | 'date'>('date');
const sortOrder = ref<'asc' | 'desc'>('desc');

// Computed properties
const processedKnowledgeFiles = computed(() => {
    return knowledgeFiles.value.filter(file => file.status !== 'uploaded');
});

const hasProcessingFiles = computed(() => {
    return knowledgeFiles.value.some(file => file.status === 'processing');
});

const filteredAndSortedFiles = computed(() => {
    let files = [...uploadingFiles.value];

    // Filter by search query
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        files = files.filter(file => {
            const fileName = file.file.name.toLowerCase();
            const mimeType = file.stats?.mime_type?.toLowerCase() || file.file.type.toLowerCase();
            return fileName.includes(query) || mimeType.includes(query);
        });
    }

    // Sort files
    files.sort((a, b) => {
        let compareValue = 0;

        switch (sortBy.value) {
            case 'name':
                compareValue = a.file.name.localeCompare(b.file.name);
                break;
            case 'size':
                compareValue = a.file.size - b.file.size;
                break;
            case 'date':
                compareValue = (a.startTime || 0) - (b.startTime || 0);
                break;
        }

        return sortOrder.value === 'asc' ? compareValue : -compareValue;
    });

    return files;
});

const allUploadingFiles = computed(() => {
    // Combine uploading files and uploaded files
    const uploading = filteredAndSortedFiles.value;
    const uploaded = uploadedFiles.value.map(file => {
        // Create a mock File object for uploaded files
        const mockFile = {
            name: file.name,
            size: file.size,
            type: file.mime_type,
            lastModified: 0,
            webkitRelativePath: '',
            arrayBuffer: async () => new ArrayBuffer(0),
            bytes: async () => new Uint8Array(0),
            slice: () => new Blob(),
            stream: () => new ReadableStream(),
            text: async () => ''
        } as File;

        return {
            id: `uploaded-${file.id}`,
            file: mockFile,
            uploading: false,
            progress: 100,
            phase: 'complete' as const,
            uploadSpeed: 0,
            bytesLoaded: file.size,
            error: undefined,
            isUploaded: true,
            uploadedFile: file
        } as UploadingFileData;
    });

    return [...uploading, ...uploaded];
});

// Selection computed properties
const selectedFilesCount = computed(() => selectedFileIds.value.size);

const isAllSelected = computed(() => {
    const visibleFiles = allUploadingFiles.value;
    if (visibleFiles.length === 0) return false;

    return visibleFiles.every(file => selectedFileIds.value.has(file.id));
});

const isIndeterminate = computed(() => {
    const visibleFiles = allUploadingFiles.value;
    if (visibleFiles.length === 0) return false;

    const selectedVisible = visibleFiles.filter(file => selectedFileIds.value.has(file.id));
    return selectedVisible.length > 0 && selectedVisible.length < visibleFiles.length;
});

// Other computed properties
const isAnyFileUploading = computed(() => {
    return uploadingFiles.value.some(file => file.uploading);
});

const hasFailedFiles = computed(() => {
    return uploadingFiles.value.some(file => file.error);
});

// Check if agent supports knowledge base functionality
const isKnowledgeAgent = computed(() => {
    return props.agent.type === 'knowledge';
});

// Page quota computed properties
const pagesRemaining = computed(() => {
    return usageData.value.pages.limit - usageData.value.pages.used;
});

const totalUploadedPages = computed(() => {
    return uploadedFiles.value.reduce((total, file) => total + (file.pages || 0), 0) +
           uploadingFiles.value.reduce((total, file) => total + (file.stats?.pages || 0), 0);
});

const wouldExceedQuota = computed(() => {
    return totalUploadedPages.value > pagesRemaining.value;
});

const quotaUtilization = computed(() => {
    const used = usageData.value.pages.used + totalUploadedPages.value;
    return Math.min((used / usageData.value.pages.limit) * 100, 100);
});

// Selection methods

const clearSelection = () => {
    selectedFileIds.value = new Set();
};

const removeFromSelection = (fileIds: string | string[]) => {
    const newSelection = new Set(selectedFileIds.value);
    const idsToRemove = Array.isArray(fileIds) ? fileIds : [fileIds];

    idsToRemove.forEach(id => newSelection.delete(id));
    selectedFileIds.value = newSelection;
};

// Watch for changes in uploadingFiles to clean up selection
watch(uploadingFiles, (newFiles) => {
    const existingIds = new Set(newFiles.map(file => file.id));
    const newSelection = new Set<string>();

    // Only keep selected IDs that still exist
    selectedFileIds.value.forEach(id => {
        if (existingIds.has(id)) {
            newSelection.add(id);
        }
    });

    selectedFileIds.value = newSelection;
}, { deep: true });

// Utility functions
const formatFileType = (mimeType: string): string => {
    const typeMap: Record<string, string> = {
        'application/pdf': 'PDF',
        'application/msword': 'Word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
        'text/plain': 'Text',
        'text/markdown': 'Markdown',
        'application/vnd.ms-excel': 'Excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel',
    };
    return typeMap[mimeType] || mimeType.split('/')[1]?.toUpperCase() || 'Unknown';
};

const formatDate = (dateString: string): string => {
    try {
        const date = new Date(dateString);

        // Check if date is valid
        if (isNaN(date.getTime())) {
            return dateString; // Return original if invalid
        }

        // Use user's locale for formatting
        return new Intl.DateTimeFormat(navigator.language || 'en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(date);
    } catch (error) {
        return dateString; // Return original if formatting fails
    }
};

const formatFileSize = (sizeInBytes: number): string => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = sizeInBytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }

    return `${size.toFixed(1)} ${units[unitIndex]}`;
};

const formatSpeed = (bytesPerSecond: number): string => {
    const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    let speed = bytesPerSecond;
    let unitIndex = 0;

    while (speed >= 1024 && unitIndex < units.length - 1) {
        speed /= 1024;
        unitIndex++;
    }

    return `${speed.toFixed(1)} ${units[unitIndex]}`;
};

const calculateETA = (file: UploadingFileData): string => {
    if (!file.startTime || !file.progress || file.progress === 0 || !file.uploadSpeed) {
        return '';
    }

    const remainingBytes = file.file.size - (file.bytesLoaded || 0);
    const remainingSeconds = remainingBytes / file.uploadSpeed;

    if (remainingSeconds < 60) {
        return `${Math.round(remainingSeconds)}s remaining`;
    } else if (remainingSeconds < 3600) {
        return `${Math.round(remainingSeconds / 60)}m remaining`;
    } else {
        return `${Math.round(remainingSeconds / 3600)}h remaining`;
    }
};

// File ID generation now handled by FileUploader component

// File upload handlers - now handled by FileUploader component

// File upload now handled by FileUploader component

// Upload functionality now handled by FileUploader component

// File management functions
const confirmDeleteUploadingFile = (fileId: string) => {
    const file = uploadingFiles.value.find(f => f.id === fileId);
    if (file) {
        uploadingFileToDelete.value = file;
        deleteUploadingFileDialogOpen.value = true;
    }
};

const confirmDeleteUploadedFile = (file: UploadedFile) => {
    // Convert UploadedFile to KnowledgeFile format for the dialog
    const knowledgeFile: KnowledgeFile = {
        id: file.id,
        name: file.name,
        size_in_kb: file.size / 1024,
        mime_type: file.mime_type,
        status: file.status,
        created_at: file.created_at
    };
    fileToDelete.value = knowledgeFile;
    deleteDialogOpen.value = true;
};

const deleteUploadingFile = () => {
    if (!uploadingFileToDelete.value) return;

    const fileId = uploadingFileToDelete.value.id;

    // Remove from FileUploader
    if (fileUploaderRef.value) {
        fileUploaderRef.value.removeFileById(fileId);
    }

    uploadingFileToDelete.value = null;
    deleteUploadingFileDialogOpen.value = false;
};

const clearAllFiles = async () => {
    try {
        // If there are uploaded files, delete them from server
        if (uploadedFiles.value.length > 0) {
            const deleteUrl = route('agents.knowledge.uploaded-files', { agent: props.agent.id });
            await axios.delete(deleteUrl);
        }

        // Clear FileUploader's state
        if (fileUploaderRef.value) {
            fileUploaderRef.value.clearAllFiles();
        }

        // Clear local state for uploaded files
        uploadedFiles.value = [];

        // Refresh the knowledge files list
        await fetchKnowledgeFiles();
    } catch (error: any) {
        console.error('Error clearing files:', error);

        // If server deletion failed, still clear FileUploader
        if (fileUploaderRef.value) {
            fileUploaderRef.value.clearAllFiles();
        }

        // Show user-friendly error message
        alert(`Failed to clear uploaded files from server: ${error.response?.data?.message || error.message}`);
    }

    clearAllDialogOpen.value = false;
};

const clearFailedFiles = () => {
    // Use FileUploader's clearFailedFiles method
    if (fileUploaderRef.value) {
        fileUploaderRef.value.clearFailedFiles();
    }
};

const confirmDeleteSelectedFiles = () => {
    if (selectedFilesCount.value > 0) {
        bulkDeleteDialogOpen.value = true;
    }
};

const confirmClearAllFiles = () => {
    const hasUploadingFiles = (fileUploaderRef.value?.totalFiles ?? 0) > 0;
    if (hasUploadingFiles || uploadedFiles.value.length > 0) {
        clearAllDialogOpen.value = true;
    }
};

const deleteSelectedFiles = () => {
    const selectedIds = Array.from(selectedFileIds.value);

    // Remove selected files from FileUploader
    if (fileUploaderRef.value) {
        selectedIds.forEach(id => {
            fileUploaderRef.value?.removeFileById(id);
        });
    }

    clearSelection();
    bulkDeleteDialogOpen.value = false;
};

// Knowledge base file management
const confirmDeleteFile = (file: KnowledgeFile) => {
    fileToDelete.value = file;
    deleteDialogOpen.value = true;
};

// Process all uploaded files
const processAllFiles = async () => {
    if (uploadedFiles.value.length === 0) {
        console.warn('No files to process');
        return;
    }

    // Check if processing would exceed page quota
    if (wouldExceedQuota.value) {
        const totalPages = totalUploadedPages.value;
        const remaining = pagesRemaining.value;

        if (!confirm(`Processing these files (${totalPages} pages) would exceed your remaining quota of ${remaining} pages. Continue anyway? Note: Only ${remaining} pages will be processed.`)) {
            return;
        }
    }

    isProcessingFiles.value = true;
    console.log(`Starting to process ${uploadedFiles.value.length} files...`);

    // Track processing state
    const processingPromises: Promise<void>[] = [];

    // Process each file (using process endpoint)
    uploadedFiles.value.forEach(file => {
        const processPromise = processFile(file);
        processingPromises.push(processPromise);
    });

    // Wait for all files to be processed
    try {
        await Promise.all(processingPromises);
        console.log('All files processed successfully');

        // Refresh the knowledge files list to show processed files
        await fetchKnowledgeFiles();
        // Refresh usage data since pages were consumed
        await fetchUsageData();
    } catch (error) {
        console.error('Some files failed to process:', error);

        // Still refresh to show any successfully processed files
        await fetchKnowledgeFiles();
        // Still refresh usage data in case some files were processed
        await fetchUsageData();
    } finally {
        isProcessingFiles.value = false;
    }
};

// Process a single file (using process endpoint)
const processFile = async (file: UploadedFile): Promise<void> => {
    try {
        console.log(`Processing file: ${file.name} (ID: ${file.id})`);

        const processUrl = route('agents.knowledge.files.process', {
            agent: props.agent.id,
            file: file.id
        });

        console.log(`PROCESS URL: ${processUrl}`);

        const response = await axios.post(processUrl);

        console.log(`Successfully processed: ${file.name}`, response);
    } catch (error: any) {
        console.error(`Failed to process file ${file.name}:`, error);

        // Log detailed error information
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response data:', error.response.data);
            console.error('Response headers:', error.response.headers);
        } else if (error.request) {
            console.error('Request made but no response:', error.request);
        } else {
            console.error('Error setting up request:', error.message);
        }

        // Log the specific error for debugging
        if (error.response?.data?.message) {
            console.error(`Error message: ${error.response.data.message}`);
        }

        // Show user-friendly error message
        alert(`Failed to process ${file.name}: ${error.response?.data?.message || error.message}`);

        throw error;
    }
};

const removeKnowledgeFile = async () => {
    if (!fileToDelete.value) return;

    try {
        console.log(`Deleting knowledge file: ${fileToDelete.value.name} (ID: ${fileToDelete.value.id})`);

        const deleteUrl = route('agents.knowledge.files.delete', {
            agent: props.agent.id,
            file: fileToDelete.value.id
        });

        console.log(`DELETE URL: ${deleteUrl}`);

        const response = await axios.delete(deleteUrl);

        console.log(`Successfully deleted: ${fileToDelete.value.name}`, response);

        // Remove file from local state
        knowledgeFiles.value = knowledgeFiles.value.filter(file => file.id !== fileToDelete.value!.id);

        // Also remove from uploadedFiles if present
        uploadedFiles.value = uploadedFiles.value.filter(file => file.id !== fileToDelete.value!.id);

    } catch (error: any) {
        console.error('Error deleting file:', error);

        // Log detailed error information
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response data:', error.response.data);
            console.error('Response headers:', error.response.headers);
        }

        // Show user-friendly error message
        alert(`Failed to delete ${fileToDelete.value.name}: ${error.response?.data?.message || error.message}`);
    }

    fileToDelete.value = null;
    deleteDialogOpen.value = false;
};

// Fetch knowledge files from the backend
const fetchKnowledgeFiles = async (isBackgroundRefresh = false) => {
    // Only show loading indicator for initial load, not background refreshes
    if (!isBackgroundRefresh) {
        isLoadingFiles.value = true;
    }

    try {
        const response = await axios.get(
            route('agents.knowledge.files', { agent: props.agent.id })
        );

        const allFiles = response.data as KnowledgeFile[];

        // Separate uploaded files from processed files
        knowledgeFiles.value = allFiles;
        uploadedFiles.value = allFiles
            .filter(file => file.status === 'uploaded')
            .map(file => ({
                id: file.id,
                name: file.name,
                size: file.size_in_kb * 1024, // Convert to bytes for consistency
                mime_type: file.mime_type,
                status: file.status,
                created_at: file.created_at,
                pages: file.number_of_pages
            }));

    } catch (error) {
        console.error('Error fetching knowledge files:', error);
    } finally {
        if (!isBackgroundRefresh) {
            isLoadingFiles.value = false;
        }
        isInitialLoad.value = false;
    }
};

// Auto-refresh logic for processing files
const startRefreshInterval = () => {
    if (refreshInterval.value) return; // Already running

    refreshInterval.value = window.setInterval(() => {
        if (hasProcessingFiles.value) {
            fetchKnowledgeFiles(true); // Pass true for background refresh
        }
    }, 3000); // Refresh every 3 seconds
};

const stopRefreshInterval = () => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
        refreshInterval.value = null;
    }
};

// Watch for processing files to start/stop auto-refresh (only for knowledge agents)
watch(hasProcessingFiles, (hasProcessing) => {
    if (isKnowledgeAgent.value && hasProcessing) {
        startRefreshInterval();
    } else {
        stopRefreshInterval();
    }
});

// Load files when component mounts (only for knowledge agents)
onMounted(() => {
    if (isKnowledgeAgent.value) {
        fetchKnowledgeFiles();
        fetchUsageData(); // Fetch usage data on mount
    }
});

// Clean up interval when component unmounts
onUnmounted(() => {
    stopRefreshInterval();
});

// Function to fetch usage data
const fetchUsageData = async () => {
    isLoadingUsage.value = true;
    try {
        const response = await axios.get(route('usage'));
        usageData.value = response.data;
    } catch (error) {
        console.error('Error fetching usage data:', error);
        toast.error('Failed to load usage data.');
    } finally {
        isLoadingUsage.value = false;
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Knowledge Base">
        <div class="space-y-6">
            <!-- Knowledge Agent Type Required Alert -->
            <Alert v-if="!isKnowledgeAgent">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    <strong>Knowledge Base Not Available</strong> This agent is configured as an instruction-based agent. Document uploading and knowledge base functionality is only available for knowledge-based agents.
                    
                </AlertDescription>
            </Alert>

            <!-- Upload Section (only for knowledge agents) -->
            <FileUploader
                v-if="isKnowledgeAgent"
                ref="fileUploaderRef"
                :config="fileUploaderConfig"
                title="Upload New Documents"
                description="Add documents to your agent's knowledge base. Supported formats: PDF, Word, Text"
                :show-quota="!isLoadingUsage"
                :quota-used="totalUploadedPages"
                :quota-limit="usageData.pages.limit"
                @files-changed="(files) => uploadingFiles = files"
            />

            <!-- Important Notes and Warnings (only for knowledge agents) -->
            <div v-if="isKnowledgeAgent" class="space-y-3">
                <!-- PDF Note -->
                <Alert>
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        <strong>PDF Tip:</strong> Ensure your PDF has selectable text (not scanned images).
                    </AlertDescription>
                </Alert>

                <!-- Quota Exceeded -->
                <Alert v-if="!isLoadingUsage && pagesRemaining <= 0" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        Quota limit reached.
                        <Link :href="route('billing.packages')" class="underline font-medium hover:no-underline text-white">
                            Upgrade to upload more
                        </Link>.
                    </AlertDescription>
                </Alert>

                <!-- Loading Usage Data -->
                <Alert v-if="isLoadingUsage">
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    <AlertDescription>
                        Loading usage information...
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Uploaded files ready for processing (only for knowledge agents) -->
            <div v-if="isKnowledgeAgent && uploadedFiles.length > 0" class="mt-4">
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">Ready to Train</CardTitle>
                                <CardDescription>
                                    {{ uploadedFiles.length }} file{{ uploadedFiles.length !== 1 ? 's' : '' }} uploaded and ready for training
                                    <span v-if="totalUploadedPages > 0" class="ml-1">
                                        • {{ totalUploadedPages }} page{{ totalUploadedPages !== 1 ? 's' : '' }}
                                    </span>
                                    <span v-if="wouldExceedQuota" class="ml-1 text-destructive font-medium">
                                        • Exceeds quota by {{ totalUploadedPages - pagesRemaining }} pages
                                    </span>
                                </CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="confirmClearAllFiles"
                                    class="text-destructive hover:text-destructive/90"
                                >
                                    <Trash2 class="h-4 w-4 mr-2" />
                                    Clear All
                                </Button>
                                <Button
                                    size="sm"
                                    @click="processAllFiles"
                                    :disabled="isProcessingFiles || uploadedFiles.length === 0 || wouldExceedQuota"
                                >
                                    <LoaderCircle v-if="isProcessingFiles" class="h-4 w-4 mr-2 animate-spin" />
                                    <Play v-else class="h-4 w-4 mr-2" />
                                    {{ isProcessingFiles ? 'Training...' : 'Train' }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Uploaded files list -->
                        <div class="space-y-2">
                            <template v-for="file in uploadedFiles" :key="file.id">
                                <div class="flex items-center justify-between border rounded-lg p-3">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <File class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-medium truncate">{{ file.name }}</span>
                                                <Badge variant="secondary" class="text-xs">
                                                    {{ formatFileType(file.mime_type) }}
                                                </Badge>
                                                <Badge variant="outline" class="text-xs">
                                                    <CheckCircle class="h-3 w-3 mr-1" />
                                                    Uploaded
                                                </Badge>
                                            </div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ formatFileSize(file.size) }}
                                                <span v-if="file.pages" class="ml-2">
                                                    • {{ file.pages }} page{{ file.pages !== 1 ? 's' : '' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="confirmDeleteUploadedFile(file)"
                                                >
                                                    <span class="sr-only">Remove file</span>
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Remove file</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Current Knowledge Base (only for knowledge agents) -->
            <Card v-if="isKnowledgeAgent">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Database class="h-5 w-5" />
                        Current Knowledge Base
                    </CardTitle>
                    <CardDescription>
                        Files trained and available to your agent. {{ processedKnowledgeFiles.length }} file{{ processedKnowledgeFiles.length !== 1 ? 's' : '' }} in total.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="isLoadingFiles && isInitialLoad" class="p-4 text-center text-muted-foreground">
                            <LoaderCircle class="mx-auto h-6 w-6 animate-spin mb-2" />
                            Loading files...
                        </div>
                        <div v-else-if="processedKnowledgeFiles.length === 0" class="p-4 text-center text-muted-foreground">
                            No files in knowledge base yet
                        </div>
                        <template v-else>
                            <ScrollArea v-if="processedKnowledgeFiles.length > 5" class="h-[400px] pr-4">
                                <div class="space-y-2">
                                    <template v-for="file in processedKnowledgeFiles" :key="file.id">
                                        <div class="flex items-center justify-between border rounded-lg p-3">
                                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                                <FileText class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-medium truncate">{{ file.name }}</span>
                                                        <Badge variant="outline" class="text-xs flex-shrink-0">
                                                            {{ formatFileType(file.mime_type) }}
                                                        </Badge>
                                                        <TooltipProvider v-if="file.status === 'processing'">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div class="flex items-center">
                                                                        <LoaderCircle class="h-4 w-4 animate-spin text-blue-500 flex-shrink-0" />
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>Processing... This file will be ready soon</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <TooltipProvider v-else-if="file.status === 'completed'">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div class="flex items-center">
                                                                        <CheckCircle class="h-4 w-4 text-green-500 flex-shrink-0" />
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>Ready to use in conversations</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </div>
                                                    <div class="text-xs text-muted-foreground">
                                                        {{ file.size_in_kb.toFixed(1) }} KB
                                                        <span v-if="file.number_of_pages" class="ml-2">
                                                            • {{ file.number_of_pages }} page{{ file.number_of_pages !== 1 ? 's' : '' }}
                                                        </span>
                                                        <span v-if="file.status === 'processing' && file.ingestion_progress !== undefined" class="ml-2">
                                                            • {{ file.ingestion_progress }}% processed
                                                        </span>
                                                        • Added {{ formatDate(file.created_at) }}
                                                    </div>
                                                    <!-- Progress bar for processing files -->
                                                    <div v-if="file.status === 'processing' && file.ingestion_progress !== undefined" class="mt-2">
                                                        <div class="w-full bg-muted rounded-full h-1.5">
                                                            <div
                                                                class="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
                                                                :style="{ width: `${file.ingestion_progress}%` }"
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                            size="sm"
                                                            variant="ghost"
                                                            @click="confirmDeleteFile(file)"
                                                        >
                                                            <span class="sr-only">Remove file</span>
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Delete from knowledge base</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                    </template>
                                </div>
                            </ScrollArea>
                            <div v-else class="space-y-2">
                                <template v-for="file in processedKnowledgeFiles" :key="file.id">
                                    <div class="flex items-center justify-between border rounded-lg p-3">
                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                            <FileText class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="font-medium truncate">{{ file.name }}</span>
                                                    <Badge variant="outline" class="text-xs flex-shrink-0">
                                                        {{ formatFileType(file.mime_type) }}
                                                    </Badge>
                                                    <TooltipProvider v-if="file.status === 'processing'">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div class="flex items-center">
                                                                    <LoaderCircle class="h-4 w-4 animate-spin text-blue-500 flex-shrink-0" />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Processing... This file will be ready soon</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <TooltipProvider v-else-if="file.status === 'completed'">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div class="flex items-center">
                                                                    <CheckCircle class="h-4 w-4 text-green-500 flex-shrink-0" />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Ready to use in conversations</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ file.size_in_kb.toFixed(1) }} KB
                                                    <span v-if="file.number_of_pages" class="ml-2">
                                                        • {{ file.number_of_pages }} page{{ file.number_of_pages !== 1 ? 's' : '' }}
                                                    </span>
                                                    <span v-if="file.status === 'processing' && file.ingestion_progress !== undefined" class="ml-2">
                                                        • {{ file.ingestion_progress }}% processed
                                                    </span>
                                                    • Added {{ formatDate(file.created_at) }}
                                                </div>
                                                <!-- Progress bar for processing files -->
                                                <div v-if="file.status === 'processing' && file.ingestion_progress !== undefined" class="mt-2">
                                                    <div class="w-full bg-muted rounded-full h-1.5">
                                                        <div
                                                            class="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
                                                            :style="{ width: `${file.ingestion_progress}%` }"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                        size="sm"
                                                        variant="ghost"
                                                        @click="confirmDeleteFile(file)"
                                                    >
                                                        <span class="sr-only">Remove file</span>
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Delete from knowledge base</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>

    <!-- Delete Confirmation Dialog for Knowledge Files -->
    <DeleteConfirmationDialog
        v-model:open="deleteDialogOpen"
        :title="`Delete ${fileToDelete?.name}?`"
        description="This will permanently remove this file from your agent's knowledge base. This action cannot be undone."
        confirm-text="Yes, delete file"
        cancel-text="Cancel"
        @confirm="removeKnowledgeFile"
    />

    <!-- Delete Confirmation Dialog for Uploading Files -->
    <DeleteConfirmationDialog
        v-model:open="deleteUploadingFileDialogOpen"
        :title="`Delete ${uploadingFileToDelete?.file.name}?`"
        description="This will cancel the upload and remove this file from the list."
        confirm-text="Yes, delete file"
        cancel-text="Cancel"
        @confirm="deleteUploadingFile"
    />

    <!-- Bulk Delete Confirmation Dialog -->
    <DeleteConfirmationDialog
        v-model:open="bulkDeleteDialogOpen"
        :title="`Delete ${selectedFilesCount} selected file${selectedFilesCount !== 1 ? 's' : ''}?`"
        description="This will remove all selected files from the uploading files list."
        confirm-text="Yes, delete files"
        cancel-text="Cancel"
        @confirm="deleteSelectedFiles"
    />

    <!-- Delete All Confirmation Dialog -->
    <DeleteConfirmationDialog
        v-model:open="clearAllDialogOpen"
        title="Clear all files?"
        description="This will remove all uploading files and permanently delete all uploaded files that haven't been processed yet. This action cannot be undone."
        confirm-text="Yes, clear all"
        cancel-text="Cancel"
        @confirm="clearAllFiles"
    />
</template>

<style>
@keyframes shimmer {
    0% {
        transform: translateX(-100%) skewX(-12deg);
    }
    100% {
        transform: translateX(200%) skewX(-12deg);
    }
}
</style>
