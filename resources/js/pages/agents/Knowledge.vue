<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Database, FileText, Upload, X, Trash2, LoaderCircle, File, AlertCircle, CheckCircle, Search, ArrowUpDown, Check, Info, Play } from 'lucide-vue-next';
import AgentLayout from '@/layouts/AgentLayout.vue';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';

import { usePage } from '@inertiajs/vue3';

const page = usePage();

interface Props {
    agent: any
    quote: {
        pages_used: number
        pages_limit: number
    }
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

interface FileStats {
    pages?: number;
    mime_type: string;
    encrypted: boolean;
}

interface UploadingFileData {
    file: File;
    uploading: boolean;
    error?: string;
    stats?: FileStats;
    id: string;
    progress: number;
    uploadSpeed: number;
    startTime?: number;
    bytesLoaded: number;
    phase?: 'uploading' | 'processing' | 'complete';
    processingInterval?: number;
    isUploaded?: boolean;
    uploadedFile?: UploadedFile;
}

// Constants
const maxFilesToUpload = 20;

const props = defineProps<Props>();

// Core state
const uploadingFiles = ref<UploadingFileData[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const knowledgeFiles = ref<KnowledgeFile[]>([]);
const uploadedFiles = ref<UploadedFile[]>([]);
const isLoadingFiles = ref(false);
const isInitialLoad = ref(true);
const isProcessingFiles = ref(false);

// Auto-refresh for processing files
const refreshInterval = ref<number | null>(null);

// Drag and drop state
const isDragOver = ref(false);
const dragCounter = ref(0);

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

// Page quota computed properties
const pagesRemaining = computed(() => {
    return props.quote.pages_limit - props.quote.pages_used;
});

const totalUploadedPages = computed(() => {
    return uploadedFiles.value.reduce((total, file) => total + (file.pages || 0), 0) +
           uploadingFiles.value.reduce((total, file) => total + (file.stats?.pages || 0), 0);
});

const wouldExceedQuota = computed(() => {
    return totalUploadedPages.value > pagesRemaining.value;
});

const quotaUtilization = computed(() => {
    const used = props.quote.pages_used + totalUploadedPages.value;
    return Math.min((used / props.quote.pages_limit) * 100, 100);
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

const generateFileId = (): string => {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
};

// File upload handlers
const triggerFileInput = () => {
    fileInputRef.value?.click();
};

// Drag and drop handlers
const handleDragEnter = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value++;

    if (e.dataTransfer?.items && e.dataTransfer.items.length > 0) {
        isDragOver.value = true;
    }
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value--;

    if (dragCounter.value === 0) {
        isDragOver.value = false;
    }
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();

    isDragOver.value = false;
    dragCounter.value = 0;

    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        // Create a mock event to reuse existing upload logic
        const mockEvent = {
            target: {
                files: files,
                value: ''
            }
        } as unknown as Event;

        handleFileUpload(mockEvent);
    }
};

const handleFileUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;
    if (!files?.length) return;

    // Check file limit
    const totalFiles = uploadingFiles.value.length + uploadedFiles.value.length + files.length;
    if (totalFiles > maxFilesToUpload) {
        alert(`You can only upload up to ${maxFilesToUpload} files at once. Please remove some files or upload in smaller batches.`);
        target.value = '';
        return;
    }

    // Check if user has any pages remaining
    if (pagesRemaining.value <= 0) {
        alert(`You have reached your page limit of ${props.quote.pages_limit} pages. Please upgrade your plan or remove some files to continue.`);
        target.value = '';
        return;
    }

    // Estimate total pages for new files (assuming average of 10 pages per file for estimation)
    const estimatedPages = files.length * 10;
    if (totalUploadedPages.value + estimatedPages > pagesRemaining.value) {
        toast.warning('Page Quota Warning', {
            description: `These files might exceed your remaining page quota of ${pagesRemaining.value} pages. You can still upload them, but processing may be limited.`,
            duration: 5000,
            closeButton: true,
        });
    }

    // Add files to the array and start uploading them
    for (let i = 0; i < files.length; i++) {
        const originalFile = files[i];
        const fileData: UploadingFileData = {
            file: originalFile,
            uploading: true,
            id: generateFileId(),
            phase: 'uploading',
            progress: 0,
            uploadSpeed: 0,
            bytesLoaded: 0,
            startTime: Date.now()
        };

        uploadingFiles.value.push(fileData);

        // Start uploading the file with a small delay to ensure UI updates
        setTimeout(() => {
            uploadFile(fileData);
        }, 100 * i);
    }

    // Reset the input to allow selecting the same file again
    target.value = '';
};

const uploadFile = async (file: UploadingFileData) => {
    let progressInterval: number | undefined;

    try {
        // Initialize progress tracking
        file.startTime = Date.now();
        file.progress = 0;
        file.bytesLoaded = 0;
        file.uploadSpeed = 0;
        file.phase = 'uploading';

        const formData = new FormData();
        formData.append('file', file.file);

        const uploadUrl = route('agents.knowledge.files.upload', { agent: props.agent.id });

        console.log(`Uploading file: ${file.file.name} to ${uploadUrl}`);

        const response = await axios.post(
            uploadUrl,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                timeout: 30000,
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total && file.startTime) {
                        clearInterval(progressInterval);

                        const uploadPercent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        const newProgress = uploadPercent;
                        const bytesLoaded = progressEvent.loaded;

                        const elapsed = (Date.now() - file.startTime) / 1000;
                        const uploadSpeed = elapsed > 0 ? progressEvent.loaded / elapsed : 0;

                        const fileIndex = uploadingFiles.value.findIndex(f => f.id === file.id);
                        if (fileIndex !== -1) {
                            uploadingFiles.value[fileIndex] = {
                                ...uploadingFiles.value[fileIndex],
                                progress: newProgress,
                                bytesLoaded: bytesLoaded,
                                uploadSpeed: uploadSpeed
                            };
                        }
                    }
                },
            }
        );

        console.log(`Successfully uploaded: ${file.file.name}`, response);

        clearInterval(progressInterval);
        if (file.processingInterval) {
            clearInterval(file.processingInterval);
        }

        // Remove from uploading files after successful upload
        uploadingFiles.value = uploadingFiles.value.filter(f => f.id !== file.id);

        // Refresh the files list to show the newly uploaded file
        await fetchKnowledgeFiles();
    } catch (error: any) {
        if (progressInterval) {
            clearInterval(progressInterval);
        }
        if (file.processingInterval) {
            clearInterval(file.processingInterval);
        }

        console.error(`Failed to upload file: ${file.file.name}`, error);

        let errorMessage = 'Failed to upload file';

        if (error.code === 'ECONNABORTED') {
            errorMessage = 'Upload timed out - file may be too large';
        } else if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat();
            if (errors.length > 0) {
                errorMessage = errors[0] as string;
            }
        }

        const fileIndex = uploadingFiles.value.findIndex(f => f.id === file.id);
        if (fileIndex !== -1) {
            uploadingFiles.value[fileIndex] = {
                ...uploadingFiles.value[fileIndex],
                uploading: false,
                progress: 0,
                phase: undefined,
                error: errorMessage
            };
        }
    }
};

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
    uploadingFiles.value = uploadingFiles.value.filter(file => file.id !== fileId);
    removeFromSelection(fileId);

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

        // Clear local state for both uploading and uploaded files
        uploadingFiles.value = [];
        uploadedFiles.value = [];
        clearSelection();

        // Refresh the knowledge files list
        await fetchKnowledgeFiles();
    } catch (error: any) {
        console.error('Error clearing files:', error);

        // If server deletion failed, still clear local uploading files
        uploadingFiles.value = [];
        clearSelection();

        // Show user-friendly error message
        alert(`Failed to clear uploaded files from server: ${error.response?.data?.message || error.message}`);
    }

    clearAllDialogOpen.value = false;
};

const clearFailedFiles = () => {
    const failedFileIds = uploadingFiles.value.filter(file => file.error).map(file => file.id);
    uploadingFiles.value = uploadingFiles.value.filter(file => !file.error);
    removeFromSelection(failedFileIds);
};

const confirmDeleteSelectedFiles = () => {
    if (selectedFilesCount.value > 0) {
        bulkDeleteDialogOpen.value = true;
    }
};

const confirmClearAllFiles = () => {
    if (uploadingFiles.value.length > 0 || uploadedFiles.value.length > 0) {
        clearAllDialogOpen.value = true;
    }
};

const deleteSelectedFiles = () => {
    const selectedIds = Array.from(selectedFileIds.value);
    uploadingFiles.value = uploadingFiles.value.filter(file => !selectedIds.includes(file.id));
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
    } catch (error) {
        console.error('Some files failed to process:', error);

        // Still refresh to show any successfully processed files
        await fetchKnowledgeFiles();
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
    }, 5000); // Refresh every 5 seconds
};

const stopRefreshInterval = () => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
        refreshInterval.value = null;
    }
};

// Watch for processing files to start/stop auto-refresh
watch(hasProcessingFiles, (hasProcessing) => {
    if (hasProcessing) {
        startRefreshInterval();
    } else {
        stopRefreshInterval();
    }
});

// Load files when component mounts
onMounted(() => {
    fetchKnowledgeFiles();
});

// Clean up interval when component unmounts
onUnmounted(() => {
    stopRefreshInterval();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Knowledge Base">
        <div class="space-y-6">
            <!-- Upload Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Upload class="h-5 w-5" />
                        Upload New Documents
                    </CardTitle>
                    <CardDescription>
                        Add documents to your agent's knowledge base. Supported formats: PDF, Word, Text
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- File count and page quota indicators -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">
                                {{ uploadingFiles.length + uploadedFiles.length }} / {{ maxFilesToUpload }} files
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">
                                    {{ props.quote.pages_used + totalUploadedPages }} / {{ props.quote.pages_limit }} pages
                                </span>
                                <div class="w-20 bg-muted rounded-full h-2">
                                    <div
                                        class="h-2 rounded-full transition-all duration-300"
                                        :class="{
                                            'bg-green-500': quotaUtilization < 70,
                                            'bg-yellow-500': quotaUtilization >= 70 && quotaUtilization < 90,
                                            'bg-red-500': quotaUtilization >= 90
                                        }"
                                        :style="{ width: `${quotaUtilization}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Page quota warning -->
                        <Alert v-if="pagesRemaining <= 0" variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                You have reached your page limit of {{ props.quote.pages_limit }} pages. Please upgrade your plan to process more documents.
                            </AlertDescription>
                        </Alert>
                        <Alert v-else-if="pagesRemaining <= 50" class="border-yellow-200 bg-yellow-50">
                            <AlertCircle class="h-4 w-4 text-yellow-600" />
                            <AlertDescription class="text-yellow-800">
                                You have {{ pagesRemaining }} pages remaining. Consider upgrading your plan if you need to process more documents.
                            </AlertDescription>
                        </Alert>

                        <input
                            ref="fileInputRef"
                            accept=".pdf,.doc,.docx,.txt,.md"
                            class="hidden"
                            multiple
                            type="file"
                            @change="handleFileUpload"
                        />

                        <div
                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center hover:bg-muted/50"
                            :class="{
                                'opacity-50 cursor-not-allowed': uploadingFiles.length + uploadedFiles.length >= maxFilesToUpload || pagesRemaining <= 0,
                                'border-destructive bg-destructive/5': pagesRemaining <= 0,
                                'border-primary bg-primary/5': isDragOver && !(uploadingFiles.length + uploadedFiles.length >= maxFilesToUpload || pagesRemaining <= 0)
                            }"
                            @click="uploadingFiles.length + uploadedFiles.length < maxFilesToUpload && pagesRemaining > 0 ? triggerFileInput() : null"
                            @dragenter="handleDragEnter"
                            @dragleave="handleDragLeave"
                            @dragover="handleDragOver"
                            @drop="handleDrop"
                        >
                            <Upload class="mb-3 h-8 w-8 text-muted-foreground" />
                            <div class="mb-1 text-base font-medium">
                                {{ pagesRemaining <= 0
                                    ? 'Page limit reached'
                                    : uploadingFiles.length + uploadedFiles.length >= maxFilesToUpload
                                        ? 'File limit reached'
                                        : 'Select files'
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ pagesRemaining <= 0
                                    ? `You've used all ${props.quote.pages_limit} pages. Upgrade your plan to continue.`
                                    : uploadingFiles.length + uploadedFiles.length >= maxFilesToUpload
                                        ? `Maximum ${maxFilesToUpload} files allowed. Remove some files to add more.`
                                        : 'Drag and drop or click to select PDF, Word, Text'
                                }}
                            </p>
                        </div>

                        <!-- PDF Note -->
                        <Alert>
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                If you are uploading a PDF, make sure you can select/highlight the text.
                            </AlertDescription>
                        </Alert>

                        <!-- Separator -->
                        <Separator v-if="uploadingFiles.length > 0 || uploadedFiles.length > 0" />

                        <!-- Files Ready to Process Section -->
                        <div v-if="uploadingFiles.length > 0 || uploadedFiles.length > 0" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>

                                    <p class="text-sm text-muted-foreground">
                                        <span v-if="totalUploadedPages > 0" class="ml-2">
                                            {{ totalUploadedPages }} page{{ totalUploadedPages !== 1 ? 's' : '' }} total
                                        </span>
                                        <span v-if="wouldExceedQuota" class="ml-2 text-destructive font-medium">
                                            • Exceeds quota by {{ totalUploadedPages - pagesRemaining }} pages
                                        </span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="uploadingFiles.length > 0 || uploadedFiles.length > 0"
                                        variant="outline"
                                        @click="confirmClearAllFiles"
                                        class="text-destructive hover:text-destructive/90 border-destructive/20 hover:border-destructive/30"
                                    >
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Clear All
                                    </Button>
                                    <Button
                                        v-if="uploadingFiles.length > 0 || uploadedFiles.length > 0"
                                        @click="processAllFiles"
                                        :disabled="isProcessingFiles || uploadedFiles.length === 0 || wouldExceedQuota"
                                    >
                                        <LoaderCircle v-if="isProcessingFiles" class="h-4 w-4 mr-2 animate-spin" />
                                        <Play v-else class="h-4 w-4 mr-2" />
                                        {{ isProcessingFiles ? 'Training...' : 'Train' }}
                                    </Button>
                                </div>
                            </div>

                            <!-- File List -->
                            <template v-if="allUploadingFiles.length > 0">
                                <ScrollArea v-if="allUploadingFiles.length > 5" class="h-[400px] pr-4">
                                    <div class="space-y-2">
                                        <template v-for="(file, index) in allUploadingFiles" :key="file.id">
                                            <div class="flex items-center justify-between border rounded-lg p-3">
                                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                                    <File class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span class="font-medium truncate">{{ file.file.name }}</span>
                                                            <Badge v-if="file.isUploaded" variant="secondary" class="text-xs">
                                                                {{ formatFileType(file.uploadedFile?.mime_type || file.file.type) }}
                                                            </Badge>
                                                            <Badge v-if="file.isUploaded" variant="outline" class="text-xs">
                                                                <CheckCircle class="h-3 w-3 mr-1" />
                                                                Uploaded
                                                            </Badge>
                                                            <Badge v-if="file.uploading" variant="outline" class="text-xs">
                                                                <LoaderCircle class="h-3 w-3 mr-1 animate-spin" />
                                                                Uploading
                                                            </Badge>
                                                            <Badge v-if="file.error" variant="destructive" class="text-xs">
                                                                <AlertCircle class="h-3 w-3 mr-1" />
                                                                Failed
                                                            </Badge>
                                                        </div>
                                                        <div class="text-xs text-muted-foreground">
                                                            {{ formatFileSize(file.file.size) }}
                                                            <span v-if="file.isUploaded && file.uploadedFile?.pages" class="ml-2">
                                                                • {{ file.uploadedFile.pages }} page{{ file.uploadedFile.pages !== 1 ? 's' : '' }}
                                                            </span>
                                                            <span v-if="file.uploading && file.progress > 0" class="ml-2">
                                                                • {{ file.progress }}% uploaded
                                                            </span>
                                                            <span v-if="file.uploading && file.uploadSpeed > 0" class="ml-2">
                                                                • {{ formatSpeed(file.uploadSpeed) }}
                                                            </span>
                                                            <span v-if="file.error" class="ml-2 text-destructive">
                                                                • {{ file.error }}
                                                            </span>
                                                        </div>
                                                        <!-- Progress bar for uploading files -->
                                                        <div v-if="file.uploading && file.progress > 0" class="mt-2">
                                                            <div class="w-full bg-muted rounded-full h-1.5">
                                                                <div
                                                                    class="bg-primary h-1.5 rounded-full transition-all duration-300"
                                                                    :style="{ width: `${file.progress}%` }"
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
                                                                @click="file.isUploaded ? confirmDeleteUploadedFile(file.uploadedFile!) : confirmDeleteUploadingFile(file.id)"
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
                                    <template v-for="(file, index) in allUploadingFiles" :key="file.id">
                                        <div class="flex items-center justify-between border rounded-lg p-3">
                                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                                <File class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-medium truncate">{{ file.file.name }}</span>
                                                        <Badge v-if="file.isUploaded" variant="secondary" class="text-xs">
                                                            {{ formatFileType(file.uploadedFile?.mime_type || file.file.type) }}
                                                        </Badge>
                                                        <Badge v-if="file.isUploaded" variant="outline" class="text-xs">
                                                            <CheckCircle class="h-3 w-3 mr-1" />
                                                            Uploaded
                                                        </Badge>
                                                        <Badge v-if="file.uploading" variant="outline" class="text-xs">
                                                            <LoaderCircle class="h-3 w-3 mr-1 animate-spin" />
                                                            Uploading
                                                        </Badge>
                                                        <Badge v-if="file.error" variant="destructive" class="text-xs">
                                                            <AlertCircle class="h-3 w-3 mr-1" />
                                                            Failed
                                                        </Badge>
                                                    </div>
                                                    <div class="text-xs text-muted-foreground">
                                                        {{ formatFileSize(file.file.size) }}
                                                        <span v-if="file.isUploaded && file.uploadedFile?.pages" class="ml-2">
                                                            • {{ file.uploadedFile.pages }} page{{ file.uploadedFile.pages !== 1 ? 's' : '' }}
                                                        </span>
                                                        <span v-if="file.uploading && file.progress > 0" class="ml-2">
                                                            • {{ file.progress }}% uploaded
                                                        </span>
                                                        <span v-if="file.uploading && file.uploadSpeed > 0" class="ml-2">
                                                            • {{ formatSpeed(file.uploadSpeed) }}
                                                        </span>
                                                        <span v-if="file.error" class="ml-2 text-destructive">
                                                            • {{ file.error }}
                                                        </span>
                                                    </div>
                                                    <!-- Progress bar for uploading files -->
                                                    <div v-if="file.uploading && file.progress > 0" class="mt-2">
                                                        <div class="w-full bg-muted rounded-full h-1.5">
                                                            <div
                                                                class="bg-primary h-1.5 rounded-full transition-all duration-300"
                                                                :style="{ width: `${file.progress}%` }"
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
                                                            @click="file.isUploaded ? confirmDeleteUploadedFile(file.uploadedFile!) : confirmDeleteUploadingFile(file.id)"
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

                            <!-- No results message -->
                            <div v-if="filteredAndSortedFiles.length === 0 && searchQuery" class="p-8 text-center text-muted-foreground">
                                <Search class="mx-auto h-12 w-12 mb-3 opacity-20" />
                                <p>No files found matching "{{ searchQuery }}"</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Current Knowledge Base -->
            <Card>
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
