<script setup lang="ts">
import FileUploader from '@/components/FileUploader.vue';
import AttachFileDialog from '@/components/ui/AttachFileDialog.vue';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import IngestionProgressBar from '@/components/ui/IngestionProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { UploadingFileData as BaseUploadingFileData, FileAnalyzedData, FileUploaderConfig } from '@/composables/useFileUploader';
import axios from 'axios';
import {
    AlertCircle,
    CheckCircle,
    Database,
    File,
    FileText,
    Info,
    Link as LinkIcon,
    LoaderCircle,
    Play,
    Trash2,
    Unlink,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';

const page = usePage();
const { hasPermission } = useTeamPermissions();

// Check if user can delete sources
const canDeleteSources = computed(() => hasPermission('agent.sources.delete'));

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
    fileType?: string; // 'pdf', 'word', or undefined for all files
    // Removed quota and usage props - will fetch from API
}

type KnowledgeFile = App.Data.Responses.Agent.SourceFileDataResponse;

interface UploadedFile {
    id: number;
    name: string;
    type: string;
    size: number;
    mime_type: string;
    status: string;
    created_at: string;
    length?: number;
    encrypted?: boolean;
}

// Extend the base interface with Knowledge.vue specific properties
interface UploadingFileData extends BaseUploadingFileData {
    isUploaded?: boolean;
    uploadedFile?: UploadedFile;
}

// Usage data interface - subset of the full usage resource with optional minutes
type UsageData = {
    pages: App.Data.Resources.My.Usage.UsageStatsData;
    minutes?: App.Data.Resources.My.Usage.UsageStatsData;
};

// File summary data interface
interface FileSummaryData {
    key_topics?: string[];
    writing_style?: string;
    target_audience?: string;
    complexity_level?: string;
    formatting_elements?: string[];
    contains_sensitive_info?: boolean;
    estimated_reading_time_minutes?: number;
}

// Constants
const maxFilesToUpload = 20;

const props = defineProps<Props>();

// WebSocket event listener for file ingestion progress updates
useEcho(`agents.${props.agent.external_id}.files.progress`, 'FileIngestionProgressEvent', (e) => {
    handleFileIngestionProgress(e);
});

// Core state
const uploadingFiles = ref<UploadingFileData[]>([]);
const fileUploaderRef = ref<InstanceType<typeof FileUploader> | null>(null);
const knowledgeFiles = ref<KnowledgeFile[]>([]);
const uploadedFiles = ref<UploadedFile[]>([]);

// Usage data state
const usageData = ref<UsageData>({
    pages: {
        limit: 0,
        used: 0,
        remaining: 0,
    },
});
const isLoadingUsage = ref(false);

// Description for file uploader based on file type
const fileUploaderDescription = computed(() => {
    if (props.fileType === 'audio') {
        return 'Upload audio files for transcription. Supported formats: MP3, M4A, WAV, OGG, WebM, FLAC, AAC, WMA, AMR, and 3GP. File size limit: 200 MB.';
    } else if (props.fileType === 'video') {
        return 'Upload video files for transcription. Supported formats: MP4, AVI, MOV, WMV, FLV, WebM, MKV, QuickTime, and 3GPP. File size limit: 200 MB.';
    }
    return 'Supported formats: PDF (.pdf), Word (.docx), PowerPoint (.pptx), Text Files (.txt), EPUB (.epub), RTF (.rtf), OpenDocument Text (.odt)';
});

// File uploader configuration for Knowledge page - dynamic based on file type
const fileUploaderConfig = computed<FileUploaderConfig>(() => {
    // Default to PDF and Word files only (for unified Files page)
    let acceptedTypes = '.pdf,.doc,.docx';
    let maxFileSize = undefined; // Default: no limit

    if (props.fileType === 'pdf') {
        acceptedTypes = '.pdf';
    } else if (props.fileType === 'word') {
        acceptedTypes = '.doc,.docx';
    } else if (props.fileType === 'markdown') {
        acceptedTypes = '.md,.markdown';
    } else if (props.fileType === 'audio') {
        acceptedTypes = '.mp3,.m4a,.wav,.ogg,.webm,.flac,.aac,.wma,.amr,.3gp';
        maxFileSize = 200; // 200 MB for audio files
    } else if (props.fileType === 'video') {
        acceptedTypes = '.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.qt,.3gp';
        maxFileSize = 200; // 200 MB for video files
    }

    return {
        maxFiles: maxFilesToUpload,
        acceptedTypes,
        maxFileSize,
        analyzeEndpoint: (file: File) => route('agents.sources.files.upload', { agent: props.agent.external_id }),
        onFileAnalyzed: (file: BaseUploadingFileData, data: FileAnalyzedData) => {
            // For Knowledge.vue, the upload endpoint uploads the file
            // Remove the file from FileUploader since it's now uploaded and will show in "Ready to Train"
            if (fileUploaderRef.value) {
                fileUploaderRef.value.removeFileById(file.id);
            }
            // Refresh the knowledge files list to show in "Ready to Train"
            fetchKnowledgeFiles();
            // Refresh usage data from server after upload
            fetchUsageData();
        },
        onFileError: (file: BaseUploadingFileData, error: string) => {
            console.error('File upload error:', error);
        },
    };
});
const isLoadingFiles = ref(false);
const isInitialLoad = ref(true);
const isProcessingFiles = ref(false);

// Selection state - using Set for better performance
const selectedFileIds = ref<Set<string>>(new Set());

// Dialog states
const deleteDialogOpen = ref(false);
const fileToDelete = ref<KnowledgeFile | null>(null);
const deleteUploadingFileDialogOpen = ref(false);
const uploadingFileToDelete = ref<UploadingFileData | null>(null);
const bulkDeleteDialogOpen = ref(false);
const clearAllDialogOpen = ref(false);
const isUnlinking = ref(false);
const isDeleting = ref(false);

// Summary dialog states
const summaryDialogOpen = ref(false);
const summaryData = ref<FileSummaryData | null>(null);
const isLoadingSummary = ref(false);
const currentSummaryFile = ref<KnowledgeFile | null>(null);

// Attach file dialog state
const attachFileDialogOpen = ref(false);

// Filter and sort state
const searchQuery = ref('');
const sortBy = ref<'name' | 'size' | 'date'>('date');
const sortOrder = ref<'asc' | 'desc'>('desc');

// Computed properties
const processedKnowledgeFiles = computed(() => {
    return knowledgeFiles.value.filter((file) => file.status !== 'uploaded');
});

const filteredAndSortedFiles = computed(() => {
    let files = [...uploadingFiles.value];

    // Filter by search query
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        files = files.filter((file) => {
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
    const uploaded = uploadedFiles.value.map((file) => {
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
            text: async () => '',
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
            uploadedFile: file,
        } as UploadingFileData;
    });

    return [...uploading, ...uploaded];
});

// Selection computed properties
const selectedFilesCount = computed(() => selectedFileIds.value.size);

const isAllSelected = computed(() => {
    const visibleFiles = allUploadingFiles.value;
    if (visibleFiles.length === 0) return false;

    return visibleFiles.every((file) => selectedFileIds.value.has(file.id));
});

const isIndeterminate = computed(() => {
    const visibleFiles = allUploadingFiles.value;
    if (visibleFiles.length === 0) return false;

    const selectedVisible = visibleFiles.filter((file) => selectedFileIds.value.has(file.id));
    return selectedVisible.length > 0 && selectedVisible.length < visibleFiles.length;
});

// Other computed properties
const isAnyFileUploading = computed(() => {
    return uploadingFiles.value.some((file) => file.uploading);
});

const hasFailedFiles = computed(() => {
    return uploadingFiles.value.some((file) => file.error);
});

// Check if agent supports knowledge base functionality
const isKnowledgeAgent = computed(() => {
    return props.agent.type === 'knowledge';
});

// Helper function to check if a file uses minutes quota (audio or video)
const usesMinutesQuota = (file: any): boolean => {
    if (!file) return false;

    // Check type field first
    if (file.type === 'audio' || file.type === 'video') return true;

    // Check MIME type as fallback for uploading files
    const mimeType = file.mime_type || file.stats?.mime_type || '';
    return mimeType.startsWith('audio/') || mimeType.startsWith('video/');
};

// Quota computed properties - use server-provided remaining values
const pagesRemaining = computed(() => {
    return usageData.value.pages.remaining;
});

const minutesRemaining = computed(() => {
    return usageData.value.minutes?.remaining ?? 0;
});

const totalUploadedPages = computed(() => {
    return (
        uploadedFiles.value.reduce((total, file) => {
            if (usesMinutesQuota(file)) {
                return total + (file.length || 0); // Use length (minutes) for audio/video files
            }
            return total + (file.length || 0); // Use length (pages) for other files
        }, 0) +
        uploadingFiles.value.reduce((total, file) => {
            if (usesMinutesQuota(file.stats)) {
                return total + (file.stats?.length || 0); // Use length (minutes) for audio/video files
            }
            return total + (file.stats?.length || 0); // Use length (pages) for other files
        }, 0)
    );
});

// Total minutes from files being uploaded and already uploaded (for display purposes)
const totalUploadedMinutes = computed(() => {
    return (
        uploadedFiles.value.reduce((total, file) => {
            if (usesMinutesQuota(file)) {
                return total + (file.length || 0);
            }
            return total;
        }, 0) +
        uploadingFiles.value.reduce((total, file) => {
            if (usesMinutesQuota(file.stats)) {
                return total + (file.stats?.length || 0);
            }
            return total;
        }, 0)
    );
});

// Minutes ONLY from files currently being uploaded (not yet on server)
const uploadingMinutesOnly = computed(() => {
    return uploadingFiles.value.reduce((total, file) => {
        if (usesMinutesQuota(file.stats)) {
            return total + (file.stats?.length || 0);
        }
        return total;
    }, 0);
});

// Pages ONLY from files currently being uploaded (not yet on server)
const uploadingPagesOnly = computed(() => {
    return uploadingFiles.value.reduce((total, file) => {
        if (!usesMinutesQuota(file.stats)) {
            return total + (file.stats?.length || 0);
        }
        return total;
    }, 0);
});

const totalUploadedPagesOnly = computed(() => {
    return (
        uploadedFiles.value.reduce((total, file) => {
            if (!usesMinutesQuota(file)) {
                // For non-audio/video files, use length as pages
                return total + (file.length || 0);
            }
            return total;
        }, 0) +
        uploadingFiles.value.reduce((total, file) => {
            if (!usesMinutesQuota(file.stats)) {
                // For non-audio/video files, use length as pages
                return total + (file.stats?.length || 0);
            }
            return total;
        }, 0)
    );
});

// Check if uploading these files would exceed quota
// Only check uploadingFiles (currently being uploaded), NOT uploadedFiles (already on server)
const wouldExceedQuota = computed(() => {
    // For audio and video files: check minutes quota
    if (props.fileType === 'audio' || props.fileType === 'video') {
        const uploadingMinutes = uploadingFiles.value.reduce((total, file) => {
            if (usesMinutesQuota(file.stats)) {
                return total + (file.stats?.length || 0);
            }
            return total;
        }, 0);
        return uploadingMinutes > minutesRemaining.value;
    }

    // For other files: check pages quota
    const uploadingPages = uploadingFiles.value.reduce((total, file) => {
        if (!usesMinutesQuota(file.stats)) {
            return total + (file.stats?.length || 0);
        }
        return total;
    }, 0);
    return uploadingPages > pagesRemaining.value;
});

const quotaUtilization = computed(() => {
    const pagesUsed = usageData.value.pages.used + totalUploadedPagesOnly.value;
    const minutesUsed = (usageData.value.minutes?.used || 0) + totalUploadedMinutes.value;

    const pagesUtilization = usageData.value.pages.limit > 0 ? (pagesUsed / usageData.value.pages.limit) * 100 : 0;
    const minutesUtilization = usageData.value.minutes?.limit ? (minutesUsed / usageData.value.minutes.limit) * 100 : 0;

    return Math.max(pagesUtilization, minutesUtilization);
});

// Selection methods

const clearSelection = () => {
    selectedFileIds.value = new Set();
};

const removeFromSelection = (fileIds: string | string[]) => {
    const newSelection = new Set(selectedFileIds.value);
    const idsToRemove = Array.isArray(fileIds) ? fileIds : [fileIds];

    idsToRemove.forEach((id) => newSelection.delete(id));
    selectedFileIds.value = newSelection;
};

// Watch for changes in uploadingFiles to clean up selection
watch(
    uploadingFiles,
    (newFiles) => {
        const existingIds = new Set(newFiles.map((file) => file.id));
        const newSelection = new Set<string>();

        // Only keep selected IDs that still exist
        selectedFileIds.value.forEach((id) => {
            if (existingIds.has(id)) {
                newSelection.add(id);
            }
        });

        selectedFileIds.value = newSelection;
    },
    { deep: true },
);

// Utility functions
const formatFileType = (mimeType: string): string => {
    const typeMap: Record<string, string> = {
        'application/pdf': 'PDF',
        'application/msword': 'Word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
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
            day: 'numeric',
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

// Helper function to format file length (pages or minutes)
const formatFileLength = (file: { length?: number; type?: string; mime_type?: string }): string => {
    if (!file.length) return '';

    // Use the same logic as usesMinutesQuota to determine if it's audio/video
    if (usesMinutesQuota(file)) {
        return `${file.length} minute${file.length !== 1 ? 's' : ''}`;
    } else {
        // For non-audio/video files, use length as pages
        return `${file.length} page${file.length !== 1 ? 's' : ''}`;
    }
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
    const file = uploadingFiles.value.find((f) => f.id === fileId);
    if (file) {
        uploadingFileToDelete.value = file;
        deleteUploadingFileDialogOpen.value = true;
    }
};

const confirmDeleteUploadedFile = (file: UploadedFile) => {
    // Check permission first
    if (!canDeleteSources.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to delete sources.',
        });
        return;
    }

    // Convert UploadedFile to KnowledgeFile format for the dialog
    const knowledgeFile: KnowledgeFile = {
        id: file.id,
        name: file.name,
        type: file.type,
        size_in_kb: file.size / 1024,
        mime_type: file.mime_type,
        status: file.status,
        created_at: file.created_at,
        length: file.length ?? 0,
        progress: 0,
        agents_count: 0,
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
            const deleteUrl = route('agents.sources.uploaded-files', { agent: props.agent.external_id });
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

        // Refresh usage data from server after clearing files
        await fetchUsageData();
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
    // Check permission first
    if (!canDeleteSources.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to delete sources.',
        });
        return;
    }

    const hasUploadingFiles = (fileUploaderRef.value?.totalFiles ?? 0) > 0;
    if (hasUploadingFiles || uploadedFiles.value.length > 0) {
        clearAllDialogOpen.value = true;
    }
};

const deleteSelectedFiles = () => {
    const selectedIds = Array.from(selectedFileIds.value);

    // Remove selected files from FileUploader
    if (fileUploaderRef.value) {
        selectedIds.forEach((id) => {
            fileUploaderRef.value?.removeFileById(id);
        });
    }

    clearSelection();
    bulkDeleteDialogOpen.value = false;
};

// Knowledge base file management
const confirmDeleteFile = (file: KnowledgeFile) => {
    // Check permission first
    if (!canDeleteSources.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to delete sources.',
        });
        return;
    }

    fileToDelete.value = file;
    deleteDialogOpen.value = true;
};

// Check if file is shared across multiple agents
const isFileShared = (file: KnowledgeFile): boolean => {
    return (file.agents_count ?? 0) > 1;
};

// Process all uploaded files
const processAllFiles = async () => {
    if (uploadedFiles.value.length === 0) {
        console.warn('No files to process');
        return;
    }

    isProcessingFiles.value = true;
    console.log(`Starting to process ${uploadedFiles.value.length} files...`);

    try {
        // Send single request to process all files
        const processUrl = route('agents.sources.files.process', {
            agent: props.agent.external_id,
        });

        console.log(`PROCESS URL: ${processUrl}`);

        const response = await axios.post(processUrl);

        console.log('All files processed successfully', response);

        // Refresh the knowledge files list to show processed files
        await fetchKnowledgeFiles();
        // Refresh usage data since pages were consumed
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to process files:', error);

        // Log detailed error information
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response data:', error.response.data);
            console.error('Response headers:', error.response.headers);
        }

        // Show user-friendly error message
        const errorMessage = error.response?.data?.message || error.message;
        alert(`Failed to process files: ${errorMessage}`);

        // Still refresh to show any successfully processed files
        await fetchKnowledgeFiles();
        // Still refresh usage data in case some files were processed
        await fetchUsageData();
    } finally {
        isProcessingFiles.value = false;
    }
};

const unlinkKnowledgeFile = async () => {
    if (!fileToDelete.value || isUnlinking.value) return;

    const fileToUnlink = fileToDelete.value;
    isUnlinking.value = true;

    try {
        console.log(`Unlinking knowledge file: ${fileToUnlink.name} (ID: ${fileToUnlink.id})`);

        const unlinkUrl = route('agents.sources.files.unlink', {
            agent: props.agent.external_id,
            fileId: fileToUnlink.id,
        });

        console.log(`UNLINK URL: ${unlinkUrl}`);

        await axios.post(unlinkUrl, {
            file_id: fileToUnlink.id,
        });

        console.log(`Successfully unlinked: ${fileToUnlink.name}`);

        // Remove file from local state
        knowledgeFiles.value = knowledgeFiles.value.filter((file) => file.id !== fileToUnlink.id);

        // Also remove from uploadedFiles if present
        uploadedFiles.value = uploadedFiles.value.filter((file) => file.id !== fileToUnlink.id);

        // Refresh usage data from server after unlinking
        await fetchUsageData();

        toast.success(`${fileToUnlink.name} has been unlinked from this agent.`);

        // Close dialog first
        deleteDialogOpen.value = false;

        // Reset state after a delay to allow dialog to close
        setTimeout(() => {
            fileToDelete.value = null;
        }, 300);
    } catch (error: any) {
        console.error('Error unlinking file:', error);

        // Log detailed error information
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response data:', error.response.data);
            console.error('Response headers:', error.response.headers);
        }

        // Show user-friendly error message
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(`Failed to unlink ${fileToUnlink.name}: ${errorMessage}`);
    } finally {
        isUnlinking.value = false;
    }
};

const removeKnowledgeFile = async () => {
    if (!fileToDelete.value || isDeleting.value) return;

    const fileToRemove = fileToDelete.value;
    isDeleting.value = true;

    try {
        console.log(`Deleting knowledge file: ${fileToRemove.name} (ID: ${fileToRemove.id})`);

        const deleteUrl = route('agents.sources.files.delete', {
            agent: props.agent.external_id,
            file: fileToRemove.id,
        });

        console.log(`DELETE URL: ${deleteUrl}`);

        const response = await axios.delete(deleteUrl);

        console.log(`Successfully deleted: ${fileToRemove.name}`, response);

        // Remove file from local state
        knowledgeFiles.value = knowledgeFiles.value.filter((file) => file.id !== fileToRemove.id);

        // Also remove from uploadedFiles if present
        uploadedFiles.value = uploadedFiles.value.filter((file) => file.id !== fileToRemove.id);

        // Refresh usage data from server after deletion
        await fetchUsageData();

        toast.success(`${fileToRemove.name} has been deleted.`);

        // Close dialog first
        deleteDialogOpen.value = false;

        // Reset state after a delay to allow dialog to close
        setTimeout(() => {
            fileToDelete.value = null;
        }, 300);
    } catch (error: any) {
        console.error('Error deleting file:', error);

        // Log detailed error information
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response data:', error.response.data);
            console.error('Response headers:', error.response.headers);
        }

        // Show user-friendly error message
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(`Failed to delete ${fileToRemove.name}: ${errorMessage}`);
    } finally {
        isDeleting.value = false;
    }
};

// Handle file ingestion progress updates from WebSocket
const handleFileIngestionProgress = (event: any) => {
    const { file_id, progress } = event;

    if (!file_id || progress === undefined) {
        return;
    }

    // Find the file and update its progress
    const fileIndex = knowledgeFiles.value.findIndex((file) => file.id === file_id);

    if (fileIndex !== -1) {
        // Create a new array with the updated file for reactivity
        const updatedFiles = [...knowledgeFiles.value];

        updatedFiles[fileIndex] = {
            ...updatedFiles[fileIndex],
            progress: progress,
            status: progress >= 100 ? 'ingested' : 'ingesting',
        };
        knowledgeFiles.value = updatedFiles;

        // If progress is 100%, update the status to 'ingested' immediately
        if (progress >= 100) {
            // Refresh files to get the complete updated data
            setTimeout(() => {
                fetchKnowledgeFiles();
            }, 1000); // Small delay to ensure backend has updated
        }
    }
};

// Fetch knowledge files from the backend
const fetchKnowledgeFiles = async () => {
    isLoadingFiles.value = true;

    try {
        // Build query params - include file_type if specified
        const params: Record<string, string> = {};
        if (props.fileType) {
            params.file_type = props.fileType;
        }

        const response = await axios.get(route('agents.sources.files.data', { agent: props.agent.external_id }), { params });

        const allFiles = response.data as KnowledgeFile[];

        // Use server data directly
        knowledgeFiles.value = allFiles;

        // Separate uploaded files from processed files
        uploadedFiles.value = allFiles
            .filter((file) => file.status === 'uploaded')
            .map((file) => ({
                id: file.id,
                name: file.name,
                type: file.type,
                size: file.size_in_kb * 1024, // Convert to bytes for consistency
                mime_type: file.mime_type,
                status: file.status,
                created_at: file.created_at,
                length: file.length,
            }));
    } catch (error) {
        console.error('Error fetching knowledge files:', error);
    } finally {
        isLoadingFiles.value = false;
        isInitialLoad.value = false;
    }
};

// Load files when component mounts
onMounted(() => {
    fetchKnowledgeFiles();
    fetchUsageData(); // Fetch usage data on mount
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

// Function to fetch and display file summary
const showFileSummary = async (file: KnowledgeFile) => {
    if (file.status !== 'ingested') {
        toast.error('Summary is only available for completed files.');
        return;
    }

    currentSummaryFile.value = file;
    summaryDialogOpen.value = true;
    isLoadingSummary.value = true;
    summaryData.value = null;

    try {
        const response = await axios.get(
            route('agents.agent.files.summary', {
                agent: props.agent.external_id,
                file: file.id,
            }),
        );

        summaryData.value = response.data;
    } catch (error: any) {
        console.error('Error fetching file summary:', error);

        if (error.response?.status === 404) {
            toast.error('No summary available for this file.');
        } else {
            toast.error('Failed to load file summary.');
        }

        summaryData.value = null;
    } finally {
        isLoadingSummary.value = false;
    }
};

// Handle file attached event
const handleFileAttached = async () => {
    // Refresh the knowledge files list to show the newly attached file
    await fetchKnowledgeFiles();
    // Refresh usage data
    await fetchUsageData();
};

// Expose methods for parent components
defineExpose({
    refreshFiles: fetchKnowledgeFiles,
});
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <!-- Upload Section -->
            <FileUploader
                ref="fileUploaderRef"
                :config="fileUploaderConfig"
                :title="
                    props.fileType === 'pdf'
                        ? 'Upload PDF Documents'
                        : props.fileType === 'word'
                          ? 'Upload Word Documents'
                          : props.fileType === 'markdown'
                            ? 'Upload Markdown Files'
                            : props.fileType === 'audio'
                              ? 'Upload Audio Files'
                              : props.fileType === 'video'
                                ? 'Upload Video Files'
                                : 'Upload New Documents'
                "
                :description="fileUploaderDescription"
                :show-quota="!isLoadingUsage"
                :quota-used="
                    props.fileType === 'audio' || props.fileType === 'video'
                        ? (usageData.minutes?.used || 0) + uploadingMinutesOnly
                        : usageData.pages.used + uploadingPagesOnly
                "
                :quota-limit="props.fileType === 'audio' || props.fileType === 'video' ? usageData.minutes?.limit || 0 : usageData.pages.limit"
                :quota-label="props.fileType === 'audio' || props.fileType === 'video' ? 'minutes' : 'pages'"
                :disabled="isProcessingFiles"
                disabled-message="File uploads are disabled while training is in progress"
                @files-changed="(files) => (uploadingFiles = files)"
            />

            <!-- Uploaded files ready for processing -->
            <div v-if="uploadedFiles.length > 0" class="mt-4">
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">Ready to Train</CardTitle>
                                <CardDescription>
                                    {{ uploadedFiles.length }} file{{ uploadedFiles.length !== 1 ? 's' : '' }} uploaded and ready for training
                                    <span v-if="totalUploadedPagesOnly > 0" class="ml-1">
                                        • {{ totalUploadedPagesOnly }} page{{ totalUploadedPagesOnly !== 1 ? 's' : '' }}
                                    </span>
                                    <span v-if="totalUploadedMinutes > 0" class="ml-1">
                                        • {{ totalUploadedMinutes }} minute{{ totalUploadedMinutes !== 1 ? 's' : '' }}
                                    </span>
                                    <span v-if="wouldExceedQuota" class="text-destructive ml-1 font-medium"> • Exceeds quota </span>
                                </CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="confirmClearAllFiles"
                                    :disabled="isProcessingFiles"
                                    class="text-destructive hover:text-destructive/90"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Clear All
                                </Button>
                                <Button
                                    size="sm"
                                    @click="processAllFiles"
                                    :disabled="isProcessingFiles || uploadedFiles.length === 0 || wouldExceedQuota"
                                >
                                    <LoaderCircle v-if="isProcessingFiles" class="mr-2 h-4 w-4 animate-spin" />
                                    <Play v-else class="mr-2 h-4 w-4" />
                                    {{ isProcessingFiles ? 'Training...' : 'Train' }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Uploaded files list -->
                        <div class="space-y-2">
                            <template v-for="file in uploadedFiles" :key="file.id">
                                <div class="flex items-center justify-between rounded-lg border p-3">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <File class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 flex items-center gap-2">
                                                <span class="truncate font-medium">{{ file.name }}</span>
                                                <Badge variant="secondary" class="text-xs">
                                                    {{ formatFileType(file.mime_type) }}
                                                </Badge>
                                                <Badge variant="outline" class="text-xs">
                                                    <CheckCircle class="mr-1 h-3 w-3" />
                                                    Ready for Training
                                                </Badge>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ formatFileSize(file.size) }}
                                                <span v-if="formatFileLength(file)" class="ml-2"> • {{ formatFileLength(file) }} </span>
                                            </div>
                                        </div>
                                    </div>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    :disabled="isProcessingFiles"
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
            <!-- Current Knowledge Base -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Database class="h-5 w-5" />
                                Trained Files
                            </CardTitle>
                            <CardDescription>
                                Files trained and available to your agent. {{ processedKnowledgeFiles.length }} file{{
                                    processedKnowledgeFiles.length !== 1 ? 's' : ''
                                }}
                                in total.
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" @click="attachFileDialogOpen = true" :disabled="isProcessingFiles">
                            <LinkIcon class="mr-2 h-4 w-4" />
                            Choose from already trained files
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="isLoadingFiles && isInitialLoad" class="text-muted-foreground p-4 text-center">
                            <LoaderCircle class="mx-auto mb-2 h-6 w-6 animate-spin" />
                            Loading files...
                        </div>
                        <div v-else-if="processedKnowledgeFiles.length === 0" class="text-muted-foreground p-4 text-center">
                            No files in knowledge base yet
                        </div>
                        <template v-else>
                            <ScrollArea v-if="processedKnowledgeFiles.length > 5" class="h-[400px] pr-4">
                                <div class="space-y-2">
                                    <template v-for="file in processedKnowledgeFiles" :key="file.id">
                                        <div class="flex items-center justify-between rounded-lg border p-3">
                                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                                <FileText class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="mb-1 flex items-center gap-2">
                                                        <span class="truncate font-medium">{{ file.name }}</span>
                                                        <Badge variant="outline" class="flex-shrink-0 text-xs">
                                                            {{ formatFileType(file.mime_type) }}
                                                        </Badge>
                                                        <TooltipProvider v-if="file.status === 'ingesting'">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div class="flex items-center">
                                                                        <LoaderCircle class="h-4 w-4 flex-shrink-0 animate-spin text-blue-500" />
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>Processing... This file will be ready soon</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <TooltipProvider v-else-if="file.status === 'ingested'">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div class="flex items-center">
                                                                        <CheckCircle class="h-4 w-4 flex-shrink-0 text-green-500" />
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>Ready to chat with</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <TooltipProvider v-else-if="file.status === 'failed'">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div class="flex items-center">
                                                                        <XCircle class="h-4 w-4 flex-shrink-0 text-red-500" />
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>Processing failed. Please contact support.</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </div>
                                                    <div class="text-muted-foreground text-xs">
                                                        {{ file.size_in_kb.toFixed(1) }} KB
                                                        <span v-if="formatFileLength(file)" class="ml-2"> • {{ formatFileLength(file) }} </span>
                                                        • Added {{ formatDate(file.created_at) }}
                                                    </div>
                                                    <!-- Progress bar for processing files -->
                                                    <IngestionProgressBar v-if="file.status === 'ingesting'" :progress="file.progress ?? 0" />
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <!-- Summary button for completed files -->
                                                <TooltipProvider v-if="file.status === 'ingested'">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="h-8 w-8 flex-shrink-0 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                size="sm"
                                                                variant="ghost"
                                                                :disabled="isProcessingFiles"
                                                                @click="showFileSummary(file)"
                                                            >
                                                                <span class="sr-only">View file summary</span>
                                                                <Info class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>View file summary</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                                size="sm"
                                                                variant="ghost"
                                                                :disabled="isProcessingFiles"
                                                                @click="confirmDeleteFile(file)"
                                                            >
                                                                <span class="sr-only">Remove file</span>
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <div class="text-xs">
                                                                <p v-if="isFileShared(file)">
                                                                    Remove from agent<br />
                                                                    <span class="text-muted-foreground"
                                                                        >(Used in {{ file.agents_count }} agents)</span
                                                                    >
                                                                </p>
                                                                <p v-else>Delete from knowledge base</p>
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </ScrollArea>
                            <div v-else class="space-y-2">
                                <template v-for="file in processedKnowledgeFiles" :key="file.id">
                                    <div class="flex items-center justify-between rounded-lg border p-3">
                                        <div class="flex min-w-0 flex-1 items-center gap-3">
                                            <FileText class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                            <div class="min-w-0 flex-1">
                                                <div class="mb-1 flex items-center gap-2">
                                                    <span class="truncate font-medium">{{ file.name }}</span>
                                                    <Badge variant="outline" class="flex-shrink-0 text-xs">
                                                        {{ formatFileType(file.mime_type) }}
                                                    </Badge>
                                                    <TooltipProvider v-if="file.status === 'ingesting'">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div class="flex items-center">
                                                                    <LoaderCircle class="h-4 w-4 flex-shrink-0 animate-spin text-blue-500" />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Processing... This file will be ready soon</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <TooltipProvider v-else-if="file.status === 'ingested'">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div class="flex items-center">
                                                                    <CheckCircle class="h-4 w-4 flex-shrink-0 text-green-500" />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Reacy to chat with</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <TooltipProvider v-else-if="file.status === 'failed'">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div class="flex items-center">
                                                                    <XCircle class="h-4 w-4 flex-shrink-0 text-red-500" />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Processing failed. Please contact support.</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                                <div class="text-muted-foreground text-xs">
                                                    {{ file.size_in_kb.toFixed(1) }} KB
                                                    <span v-if="formatFileLength(file)" class="ml-2"> • {{ formatFileLength(file) }} </span>
                                                    • Added {{ formatDate(file.created_at) }}
                                                </div>
                                                <!-- Progress bar for processing files -->
                                                <IngestionProgressBar v-if="file.status === 'ingesting'" :progress="file.progress ?? 0" />
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <!-- Summary button for completed files -->
                                            <TooltipProvider v-if="file.status === 'ingested'">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            class="h-8 w-8 flex-shrink-0 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                            size="sm"
                                                            variant="ghost"
                                                            :disabled="isProcessingFiles"
                                                            @click="showFileSummary(file)"
                                                        >
                                                            <span class="sr-only">View file summary</span>
                                                            <Info class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>View file summary</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                            size="sm"
                                                            variant="ghost"
                                                            :disabled="isProcessingFiles"
                                                            @click="confirmDeleteFile(file)"
                                                        >
                                                            <span class="sr-only">Remove file</span>
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <div class="text-xs">
                                                            <p v-if="isFileShared(file)">
                                                                Remove from agent<br />
                                                                <span class="text-muted-foreground">(Used in {{ file.agents_count }} agents)</span>
                                                            </p>
                                                            <p v-else>Delete from knowledge base</p>
                                                        </div>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <!-- Delete/Unlink Confirmation Dialog for Knowledge Files -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent v-if="fileToDelete" class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ fileToDelete.name }}</DialogTitle>
                        <DialogDescription>
                            <span v-if="isFileShared(fileToDelete)">
                                This file is used in <strong>{{ fileToDelete.agents_count }} agents</strong>. You can unlink it from this agent or
                                permanently delete it from all agents.
                            </span>
                            <span v-else>
                                This will permanently remove this file from your agent's knowledge base. This action cannot be undone.
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <Button variant="outline" @click="deleteDialogOpen = false" :disabled="isUnlinking || isDeleting"> Cancel </Button>
                        <Button
                            v-if="isFileShared(fileToDelete)"
                            variant="secondary"
                            @click="unlinkKnowledgeFile"
                            :disabled="isUnlinking || isDeleting"
                            class="bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
                        >
                            <LoaderCircle v-if="isUnlinking" class="mr-2 h-4 w-4 animate-spin" />
                            <Unlink v-else class="mr-2 h-4 w-4" />
                            {{ isUnlinking ? 'Unlinking...' : 'Unlink' }}
                        </Button>
                        <Button variant="destructive" @click="removeKnowledgeFile" :disabled="isUnlinking || isDeleting">
                            <LoaderCircle v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                            <Trash2 v-else class="mr-2 h-4 w-4" />
                            <span v-if="isDeleting">Deleting...</span>
                            <span v-else>{{ isFileShared(fileToDelete) ? 'Delete permanently' : 'Delete' }}</span>
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

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

            <!-- File Summary Dialog -->
            <Dialog v-model:open="summaryDialogOpen">
                <DialogContent class="max-h-[80vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Info class="h-5 w-5 text-blue-600" />
                            File Summary: {{ currentSummaryFile?.name }}
                        </DialogTitle>
                        <DialogDescription> AI-generated analysis of the document content and structure. </DialogDescription>
                    </DialogHeader>

                    <div v-if="isLoadingSummary" class="flex items-center justify-center py-8">
                        <LoaderCircle class="h-8 w-8 animate-spin text-blue-600" />
                        <span class="text-muted-foreground ml-2">Loading summary...</span>
                    </div>

                    <div v-else-if="summaryData" class="space-y-6">
                        <!-- Key Topics -->
                        <div v-if="summaryData.key_topics && summaryData.key_topics.length > 0">
                            <h3 class="text-foreground mb-2 text-sm font-semibold">Key Topics</h3>
                            <div class="flex flex-wrap gap-2">
                                <Badge v-for="topic in summaryData.key_topics" :key="topic" variant="secondary" class="text-xs">
                                    {{ topic }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Document Characteristics -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div v-if="summaryData.writing_style">
                                <h3 class="text-foreground mb-1 text-sm font-semibold">Writing Style</h3>
                                <p class="text-muted-foreground text-sm capitalize">{{ summaryData.writing_style }}</p>
                            </div>

                            <div v-if="summaryData.target_audience">
                                <h3 class="text-foreground mb-1 text-sm font-semibold">Target Audience</h3>
                                <p class="text-muted-foreground text-sm capitalize">{{ summaryData.target_audience }}</p>
                            </div>

                            <div v-if="summaryData.complexity_level">
                                <h3 class="text-foreground mb-1 text-sm font-semibold">Complexity Level</h3>
                                <p class="text-muted-foreground text-sm capitalize">{{ summaryData.complexity_level }}</p>
                            </div>

                            <div v-if="summaryData.estimated_reading_time_minutes">
                                <h3 class="text-foreground mb-1 text-sm font-semibold">Estimated Reading Time</h3>
                                <p class="text-muted-foreground text-sm">{{ summaryData.estimated_reading_time_minutes }} minutes</p>
                            </div>
                        </div>

                        <!-- Formatting Elements -->
                        <div v-if="summaryData.formatting_elements && summaryData.formatting_elements.length > 0">
                            <h3 class="text-foreground mb-2 text-sm font-semibold">Formatting Elements</h3>
                            <div class="flex flex-wrap gap-2">
                                <Badge v-for="element in summaryData.formatting_elements" :key="element" variant="outline" class="text-xs">
                                    {{ element.replace(/_/g, ' ') }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Sensitive Information Warning -->
                        <div v-if="summaryData.contains_sensitive_info" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                            <div class="flex items-center gap-2">
                                <AlertCircle class="h-4 w-4 text-yellow-600" />
                                <h3 class="text-sm font-semibold text-yellow-800">Sensitive Information Detected</h3>
                            </div>
                            <p class="mt-1 text-sm text-yellow-700">This document may contain sensitive or confidential information.</p>
                        </div>
                    </div>

                    <div v-else class="text-muted-foreground py-8 text-center">
                        <Info class="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p>No summary data available for this file.</p>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Attach File Dialog -->
            <AttachFileDialog
                v-model:open="attachFileDialogOpen"
                :agent-id="agent.external_id"
                :file-type="fileType"
                @file-attached="handleFileAttached"
            />
        </div>
    </div>
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
