/**
 * State and handlers behind the knowledge-base file picker (FileUploader.vue):
 * drag and drop, per-file limits, upload progress and the server's analysis of
 * each file (pages, length, encryption). Uploads go through `@/dashboard/agents/api/uploads`.
 */
import { analyzeUploadedFile } from '@/dashboard/agents/api/uploads';
import { errorMessage } from '@/shared/api/http';
import { isAxiosError } from 'axios';
import { computed, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

/** What the server answers an upload with: a file's analysis, or what it did with an archive. */
export type FileAnalyzedData = Domain.Agent.Data.Sources.KnowledgeAnalyzeResourceData | ArchiveUploadResult;

export type ArchiveUploadResult = Domain.Knowledge.Source.File.Data.ArchiveUploadResultData;

export function isArchiveUploadResult(data: FileAnalyzedData): data is ArchiveUploadResult {
    return 'uploaded' in data && 'skipped' in data;
}

export interface FileStats {
    pages?: number;
    length?: number;
    is_audio_file?: boolean;
    is_video_file?: boolean;
    mime_type: string;
    filetype?: string;
    encrypted?: boolean;
}

export interface UploadingFileData {
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
    isAnalyzed?: boolean;
    analyzedData?: FileAnalyzedData;
}

export interface FileUploaderConfig {
    maxFiles: number;
    analyzeEndpoint: (file: File) => string;
    acceptedTypes: string;
    maxFileSize?: number; // in MB
    onFileAnalyzed?: (file: UploadingFileData, data: FileAnalyzedData) => void;
    onFileError?: (file: UploadingFileData, error: string) => void;
}

export function useFileUploader(config: FileUploaderConfig) {
    const { t } = useI18n();
    const uploadingFiles = ref<UploadingFileData[]>([]);
    const isDragOver = ref(false);
    const dragCounter = ref(0);

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
            'application/zip': 'ZIP',
            'application/x-zip-compressed': 'ZIP',
        };
        return typeMap[mimeType] || mimeType.split('/')[1]?.toUpperCase() || 'Unknown';
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

    const generateFileId = (): string => {
        return Date.now().toString(36) + Math.random().toString(36).slice(2);
    };

    // Computed properties
    const isAnyFileUploading = computed(() => {
        return uploadingFiles.value.some((file) => file.uploading);
    });

    const hasFailedFiles = computed(() => {
        return uploadingFiles.value.some((file) => file.error);
    });

    const totalFiles = computed(() => uploadingFiles.value.length);

    const totalPages = computed(() => {
        return uploadingFiles.value.reduce((total, file) => total + (file.stats?.length || 0), 0);
    });

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
            addFiles(Array.from(files));
        }
    };

    // File management
    const addFiles = async (files: File[]) => {
        // Check file limit
        if (uploadingFiles.value.length + files.length > config.maxFiles) {
            toast.error(t('shared.file_uploader.toast_max_files_title'), {
                description: t('shared.file_uploader.toast_max_files_description', { max: config.maxFiles }),
                duration: 5000,
                position: 'top-center',
            });
            return;
        }

        // Check file size if maxFileSize is specified
        if (config.maxFileSize) {
            const maxSizeBytes = config.maxFileSize * 1024 * 1024; // Convert MB to bytes
            const oversizedFiles = files.filter((file) => file.size > maxSizeBytes);

            if (oversizedFiles.length > 0) {
                const fileList = oversizedFiles.map((f) => f.name).join(', ');
                toast.error(t('shared.file_uploader.toast_file_size_title'), {
                    description: t('shared.file_uploader.toast_file_size_description', { maxSize: config.maxFileSize, fileList }),
                    duration: 7000,
                    position: 'top-center',
                });
                return;
            }
        }

        // Add files to the array and start analyzing them
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
                startTime: Date.now(),
            };

            uploadingFiles.value.push(fileData);

            // Start analyzing the file with a small delay to ensure UI updates
            setTimeout(() => {
                analyzeFile(fileData);
            }, 100 * i);
        }
    };

    // A timeout, then the server's message, then its first validation error.
    const describeAnalyzeError = (error: unknown): string => {
        if (isAxiosError(error) && error.code === 'ECONNABORTED') {
            return t('shared.file_uploader.analyze_timed_out');
        }

        const fallback = t('shared.file_uploader.analyze_failed');
        const message = errorMessage(error, '');
        if (message) return message;

        if (isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.data?.errors) {
            const [firstError] = Object.values(error.response.data.errors).flat();

            return firstError ?? fallback;
        }

        return fallback;
    };

    const analyzeFile = async (file: UploadingFileData) => {
        try {
            file.startTime = Date.now();
            file.progress = 0;
            file.bytesLoaded = 0;
            file.uploadSpeed = 0;
            file.phase = 'uploading';

            const analyzed = await analyzeUploadedFile<FileAnalyzedData>(config.analyzeEndpoint(file.file), file.file, (progressEvent) => {
                if (progressEvent.total && file.startTime) {
                    const uploadPercent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    const bytesLoaded = progressEvent.loaded;
                    const elapsed = (Date.now() - file.startTime) / 1000;
                    const uploadSpeed = elapsed > 0 ? progressEvent.loaded / elapsed : 0;

                    const fileIndex = uploadingFiles.value.findIndex((f) => f.id === file.id);
                    if (fileIndex !== -1) {
                        uploadingFiles.value[fileIndex] = {
                            ...uploadingFiles.value[fileIndex],
                            progress: uploadPercent,
                            bytesLoaded: bytesLoaded,
                            uploadSpeed: uploadSpeed,
                        };
                    }
                }
            });

            // Update file with analysis results
            const fileIndex = uploadingFiles.value.findIndex((f) => f.id === file.id);
            if (fileIndex !== -1) {
                uploadingFiles.value[fileIndex] = {
                    ...uploadingFiles.value[fileIndex],
                    uploading: false,
                    progress: 100,
                    phase: 'complete',
                    isAnalyzed: true,
                    analyzedData: analyzed,
                    // The analysis reports no page count or encryption flag, so those keep their defaults.
                    stats: {
                        pages: 0,
                        length: analyzed.length || 0,
                        is_audio_file: !isArchiveUploadResult(analyzed) && analyzed.is_audio_file,
                        mime_type: (!isArchiveUploadResult(analyzed) && analyzed.mime_type) || file.file.type,
                        encrypted: false,
                    },
                };

                config.onFileAnalyzed?.(uploadingFiles.value[fileIndex], analyzed);
            }
        } catch (error) {
            const message = describeAnalyzeError(error);

            const fileIndex = uploadingFiles.value.findIndex((f) => f.id === file.id);
            if (fileIndex !== -1) {
                uploadingFiles.value[fileIndex] = {
                    ...uploadingFiles.value[fileIndex],
                    uploading: false,
                    progress: 0,
                    phase: undefined,
                    error: message,
                };

                config.onFileError?.(uploadingFiles.value[fileIndex], message);
            }
        }
    };

    const removeFile = (fileId: string) => {
        uploadingFiles.value = uploadingFiles.value.filter((file) => file.id !== fileId);
    };

    const clearAllFiles = () => {
        uploadingFiles.value = [];
    };

    const clearFailedFiles = () => {
        uploadingFiles.value = uploadingFiles.value.filter((file) => !file.error);
    };

    // Cleanup
    onUnmounted(() => {
        // Clear any intervals or ongoing uploads
        uploadingFiles.value.forEach((file) => {
            if (file.processingInterval) {
                clearInterval(file.processingInterval);
            }
        });
    });

    return {
        // State
        uploadingFiles,
        isDragOver,

        // Computed
        isAnyFileUploading,
        hasFailedFiles,
        totalFiles,
        totalPages,

        // Methods
        addFiles,
        removeFile,
        clearAllFiles,
        clearFailedFiles,

        // Drag & Drop
        handleDragEnter,
        handleDragLeave,
        handleDragOver,
        handleDrop,

        // Utilities
        formatFileType,
        formatFileSize,
        formatSpeed,
    };
}
