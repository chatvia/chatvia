import axios from 'axios';
import { computed, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

export type FileAnalyzedData = App.Data.Agent.KnowledgeAnalyzeResourceData;

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
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
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
            toast.error('Maximum file limit reached', {
                description: `You can only upload a maximum of ${config.maxFiles} files.`,
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
                toast.error('File size limit exceeded', {
                    description: `The following file(s) exceed the ${config.maxFileSize} MB limit: ${fileList}`,
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

    const analyzeFile = async (file: UploadingFileData) => {
        try {
            file.startTime = Date.now();
            file.progress = 0;
            file.bytesLoaded = 0;
            file.uploadSpeed = 0;
            file.phase = 'uploading';

            const formData = new FormData();
            formData.append('file', file.file);

            const endpoint = config.analyzeEndpoint(file.file);

            const response = await axios.post(endpoint, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                timeout: 30000,
                onUploadProgress: (progressEvent) => {
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
                },
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
                    analyzedData: response.data,
                    stats: {
                        pages: response.data.pages || 0,
                        length: response.data.length || 0,
                        is_audio_file: response.data.is_audio_file || false,
                        mime_type: response.data.mime_type || file.file.type,
                        encrypted: response.data.encrypted || false,
                    },
                };

                config.onFileAnalyzed?.(uploadingFiles.value[fileIndex], response.data);
            }
        } catch (error: any) {
            console.error(`Failed to analyze file: ${file.file.name}`, error);

            let errorMessage = 'Failed to analyze file';

            if (error.code === 'ECONNABORTED') {
                errorMessage = 'Analysis timed out - file may be too large';
            } else if (error.response?.data?.message) {
                errorMessage = error.response.data.message;
            } else if (error.response?.data?.errors) {
                const errors = Object.values(error.response.data.errors).flat();
                if (errors.length > 0) {
                    errorMessage = errors[0] as string;
                }
            }

            const fileIndex = uploadingFiles.value.findIndex((f) => f.id === file.id);
            if (fileIndex !== -1) {
                uploadingFiles.value[fileIndex] = {
                    ...uploadingFiles.value[fileIndex],
                    uploading: false,
                    progress: 0,
                    phase: undefined,
                    error: errorMessage,
                };

                config.onFileError?.(uploadingFiles.value[fileIndex], errorMessage);
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
