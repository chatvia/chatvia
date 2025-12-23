<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFileUploader, type FileAnalyzedData, type FileUploaderConfig, type UploadingFileData } from '@/composables/useFileUploader';
import { AlertCircle, CheckCircle, File, LoaderCircle, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    title?: string;
    description?: string;
    config: FileUploaderConfig;
    showQuota?: boolean;
    quotaUsed?: number;
    quotaLimit?: number;
    quotaLabel?: string;
    disabled?: boolean;
    disabledMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Upload Files',
    description: 'Add files to analyze',
    showQuota: false,
    quotaUsed: 0,
    quotaLimit: 100,
    quotaLabel: 'pages',
    disabled: false,
    disabledMessage: 'Upload disabled',
});

const emit = defineEmits<{
    filesChanged: [files: UploadingFileData[]];
    fileAnalyzed: [file: UploadingFileData, data: FileAnalyzedData];
    fileError: [file: UploadingFileData, error: string];
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);

// Configure the uploader
const uploaderConfig: FileUploaderConfig = {
    ...props.config,
    onFileAnalyzed: (file: UploadingFileData, data: FileAnalyzedData) => {
        emit('fileAnalyzed', file, data);
        emit('filesChanged', uploadingFiles.value);
        props.config.onFileAnalyzed?.(file, data);
    },
    onFileError: (file: UploadingFileData, error: string) => {
        emit('fileError', file, error);
        emit('filesChanged', uploadingFiles.value);
        props.config.onFileError?.(file, error);
    },
};

const {
    uploadingFiles,
    isDragOver,
    isAnyFileUploading,
    hasFailedFiles,
    totalFiles,
    totalPages,
    addFiles,
    removeFile,
    clearAllFiles,
    clearFailedFiles,
    handleDragEnter,
    handleDragLeave,
    handleDragOver,
    handleDrop,
    formatFileType,
    formatFileSize,
    formatSpeed,
} = useFileUploader(uploaderConfig);

// Helper function to format file length (pages or duration)
const formatFileLength = (file: UploadingFileData): string => {
    if (!file.stats?.length) return '';

    if (file.stats.is_audio_file) {
        return `${file.stats.length} minute${file.stats.length !== 1 ? 's' : ''}`;
    } else {
        return `${file.stats.length} page${file.stats.length !== 1 ? 's' : ''}`;
    }
};

// Quota computations
const quotaRemaining = computed(() => {
    if (!props.showQuota) return Infinity;
    return props.quotaLimit - props.quotaUsed;
});

const quotaUtilization = computed(() => {
    if (!props.showQuota) return 0;
    const used = props.quotaUsed + totalPages.value;
    return Math.min((used / props.quotaLimit) * 100, 100);
});

const wouldExceedQuota = computed(() => {
    if (!props.showQuota) return false;
    return totalPages.value > quotaRemaining.value;
});

// UI state
const canUpload = computed(() => {
    return !props.disabled && totalFiles.value < props.config.maxFiles && (!props.showQuota || quotaRemaining.value > 0);
});

const triggerFileInput = () => {
    if (canUpload.value) {
        fileInputRef.value?.click();
    }
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;
    if (!files?.length) return;

    addFiles(Array.from(files));
    target.value = ''; // Reset input
};

// Watch files and emit changes
const onRemoveFile = (fileId: string) => {
    removeFile(fileId);
    emit('filesChanged', uploadingFiles.value);
};

const onClearAllFiles = () => {
    clearAllFiles();
    emit('filesChanged', uploadingFiles.value);
};

// Methods to expose to parent
const removeFileById = (fileId: string) => {
    removeFile(fileId);
    emit('filesChanged', uploadingFiles.value);
};

// Expose methods to parent
defineExpose({
    uploadingFiles,
    totalFiles,
    totalPages,
    clearAllFiles: onClearAllFiles,
    clearFailedFiles,
    removeFileById,
    isAnyFileUploading,
    hasFailedFiles,
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Upload class="h-5 w-5" />
                {{ title }}
            </CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <!-- File count and quota indicators -->
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-sm"> {{ totalFiles }} / {{ config.maxFiles }} files </span>
                    <div v-if="showQuota" class="flex items-center gap-2">
                        <span class="text-muted-foreground text-sm"> {{ quotaUsed + totalPages }} / {{ quotaLimit }} {{ quotaLabel }} </span>
                        <div class="bg-muted h-2 w-20 rounded-full">
                            <div
                                class="h-2 rounded-full transition-all duration-300"
                                :class="{
                                    'bg-green-500': quotaUtilization < 70,
                                    'bg-yellow-500': quotaUtilization >= 70 && quotaUtilization < 90,
                                    'bg-red-500': quotaUtilization >= 90,
                                }"
                                :style="{ width: `${quotaUtilization}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Quota warnings -->
                <Alert v-if="showQuota && quotaRemaining <= 0" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        You have reached your {{ quotaLabel }} limit of {{ quotaLimit }} {{ quotaLabel }}. Please upgrade your plan to process more
                        documents.
                    </AlertDescription>
                </Alert>

                <!-- File input -->
                <input ref="fileInputRef" :accept="config.acceptedTypes" class="hidden" multiple type="file" @change="handleFileUpload" />

                <!-- Drop zone -->
                <div
                    class="hover:bg-muted/50 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center"
                    :class="{
                        'cursor-not-allowed opacity-50': disabled,
                        'cursor-not-allowed': !canUpload && !disabled,
                        'border-destructive bg-destructive/5': !disabled && showQuota && quotaRemaining <= 0,
                        'border-primary bg-primary/5': isDragOver && canUpload,
                    }"
                    @click="triggerFileInput"
                    @dragenter="handleDragEnter"
                    @dragleave="handleDragLeave"
                    @dragover="handleDragOver"
                    @drop="handleDrop"
                >
                    <Upload class="text-muted-foreground mb-3 h-8 w-8" />
                    <div class="mb-1 text-base font-medium">
                        {{
                            !canUpload && !disabled
                                ? totalFiles >= config.maxFiles
                                    ? 'File limit reached'
                                    : `${quotaLabel.charAt(0).toUpperCase() + quotaLabel.slice(1)} limit reached`
                                : 'Select files'
                        }}
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{
                            !canUpload && !disabled
                                ? totalFiles >= config.maxFiles
                                    ? `Maximum ${config.maxFiles} files allowed. Remove some files to add more.`
                                    : showQuota
                                      ? `You've used all ${quotaLimit} ${quotaLabel}. Upgrade your plan to continue.`
                                      : 'Upload not available'
                                : 'Drag and drop or click to select files'
                        }}
                    </p>
                </div>

                <!-- Files list -->
                <template v-if="uploadingFiles.length > 0">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-muted-foreground text-sm">
                                <span v-if="totalPages > 0">
                                    {{ totalPages }} {{ totalPages !== 1 ? quotaLabel : quotaLabel.replace(/s$/, '') }} total
                                </span>
                                <span v-if="showQuota && wouldExceedQuota" class="text-destructive ml-2 font-medium">
                                    • Exceeds quota by {{ totalPages - quotaRemaining }} {{ quotaLabel }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="hasFailedFiles"
                                variant="outline"
                                size="sm"
                                @click="clearFailedFiles"
                                class="text-yellow-600 hover:text-yellow-700"
                            >
                                Clear Failed
                            </Button>
                            <Button variant="outline" size="sm" @click="onClearAllFiles" class="text-destructive hover:text-destructive/90">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Clear All
                            </Button>
                        </div>
                    </div>

                    <ScrollArea v-if="uploadingFiles.length > 5" class="h-[400px] pr-4">
                        <div class="space-y-2">
                            <template v-for="file in uploadingFiles" :key="file.id">
                                <div class="flex items-center justify-between rounded-lg border p-3">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <File class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 flex items-center gap-2">
                                                <span class="truncate font-medium">{{ file.file.name }}</span>
                                                <Badge v-if="file.isAnalyzed" variant="secondary" class="text-xs">
                                                    {{ formatFileType(file.stats?.mime_type || file.file.type) }}
                                                </Badge>
                                                <Badge v-if="file.isAnalyzed" variant="outline" class="text-xs">
                                                    <CheckCircle class="mr-1 h-3 w-3" />
                                                    Analyzed
                                                </Badge>
                                                <Badge v-if="file.uploading" variant="outline" class="text-xs">
                                                    <LoaderCircle class="mr-1 h-3 w-3 animate-spin" />
                                                    Analyzing
                                                </Badge>
                                                <Badge v-if="file.error" variant="destructive" class="text-xs">
                                                    <AlertCircle class="mr-1 h-3 w-3" />
                                                    Failed
                                                </Badge>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ formatFileSize(file.file.size) }}
                                                <span v-if="formatFileLength(file)" class="ml-2"> • {{ formatFileLength(file) }} </span>
                                                <span v-if="file.uploading && file.progress > 0" class="ml-2"> • {{ file.progress }}% analyzed </span>
                                                <span v-if="file.uploading && file.uploadSpeed > 0" class="ml-2">
                                                    • {{ formatSpeed(file.uploadSpeed) }}
                                                </span>
                                                <span v-if="file.error" class="text-destructive ml-2"> • {{ file.error }} </span>
                                            </div>
                                            <!-- Progress bar -->
                                            <div v-if="file.uploading && file.progress > 0" class="mt-2">
                                                <div class="bg-muted h-1.5 w-full rounded-full">
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
                                                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="onRemoveFile(file.id)"
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
                    </ScrollArea>
                    <div v-else class="space-y-2">
                        <template v-for="file in uploadingFiles" :key="file.id">
                            <div class="flex items-center justify-between rounded-lg border p-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <File class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex items-center gap-2">
                                            <span class="truncate font-medium">{{ file.file.name }}</span>
                                            <Badge v-if="file.isAnalyzed" variant="secondary" class="text-xs">
                                                {{ formatFileType(file.stats?.mime_type || file.file.type) }}
                                            </Badge>
                                            <Badge v-if="file.isAnalyzed" variant="outline" class="text-xs">
                                                <CheckCircle class="mr-1 h-3 w-3" />
                                                Analyzed
                                            </Badge>
                                            <Badge v-if="file.uploading" variant="outline" class="text-xs">
                                                <LoaderCircle class="mr-1 h-3 w-3 animate-spin" />
                                                Analyzing
                                            </Badge>
                                            <Badge v-if="file.error" variant="destructive" class="text-xs">
                                                <AlertCircle class="mr-1 h-3 w-3" />
                                                Failed
                                            </Badge>
                                        </div>
                                        <div class="text-muted-foreground text-xs">
                                            {{ formatFileSize(file.file.size) }}
                                            <span v-if="formatFileLength(file)" class="ml-2"> • {{ formatFileLength(file) }} </span>
                                            <span v-if="file.uploading && file.progress > 0" class="ml-2"> • {{ file.progress }}% analyzed </span>
                                            <span v-if="file.uploading && file.uploadSpeed > 0" class="ml-2">
                                                • {{ formatSpeed(file.uploadSpeed) }}
                                            </span>
                                            <span v-if="file.error" class="text-destructive ml-2"> • {{ file.error }} </span>
                                        </div>
                                        <!-- Progress bar -->
                                        <div v-if="file.uploading && file.progress > 0" class="mt-2">
                                            <div class="bg-muted h-1.5 w-full rounded-full">
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
                                                class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                size="sm"
                                                variant="ghost"
                                                @click="onRemoveFile(file.id)"
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
                </template>
            </div>
        </CardContent>
    </Card>
</template>
