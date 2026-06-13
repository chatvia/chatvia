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
import { useI18n } from 'vue-i18n';

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
    showQuota: false,
    quotaUsed: 0,
    quotaLimit: 100,
    disabled: false,
});

const { t } = useI18n();

const displayTitle = computed(() => props.title ?? t('shared.file_uploader.title_default'));
const displayDescription = computed(() => props.description ?? t('shared.file_uploader.description_default'));
const displayQuotaLabel = computed(() => props.quotaLabel ?? t('shared.file_uploader.quota_label_default'));

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

const quotaLabelForCount = (count: number): string => {
    const label = displayQuotaLabel.value;
    if (count === 1 && label.endsWith('s')) {
        return label.slice(0, -1);
    }

    return label;
};

// Helper function to format file length (pages or duration)
const formatFileLength = (file: UploadingFileData): string => {
    if (!file.stats?.length) return '';

    if (file.stats.is_audio_file) {
        return t('shared.file_uploader.minute_length', { count: file.stats.length }, { plural: file.stats.length });
    }

    return t('shared.file_uploader.page_length', { count: file.stats.length }, { plural: file.stats.length });
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

const dropZoneTitle = computed(() => {
    if (!canUpload.value && !props.disabled) {
        if (totalFiles.value >= props.config.maxFiles) {
            return t('shared.file_uploader.drop_zone_file_limit_reached');
        }

        return t('shared.file_uploader.drop_zone_quota_limit_reached', {
            quotaLabel: displayQuotaLabel.value.charAt(0).toUpperCase() + displayQuotaLabel.value.slice(1),
        });
    }

    return t('shared.file_uploader.drop_zone_select_files');
});

const dropZoneHint = computed(() => {
    if (!canUpload.value && !props.disabled) {
        if (totalFiles.value >= props.config.maxFiles) {
            return t('shared.file_uploader.drop_zone_max_files_hint', { max: props.config.maxFiles });
        }

        if (props.showQuota) {
            return t('shared.file_uploader.drop_zone_quota_exhausted_hint', {
                quotaLimit: props.quotaLimit,
                quotaLabel: displayQuotaLabel.value,
            });
        }

        return t('shared.file_uploader.drop_zone_unavailable');
    }

    return t('shared.file_uploader.drop_zone_default_hint');
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
                {{ displayTitle }}
            </CardTitle>
            <CardDescription>{{ displayDescription }}</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <!-- File count and quota indicators -->
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-sm">
                        {{ t('shared.file_uploader.file_count', { current: totalFiles, max: config.maxFiles }) }}
                    </span>
                    <div v-if="showQuota" class="flex items-center gap-2">
                        <span class="text-muted-foreground text-sm"> {{ quotaUsed + totalPages }} / {{ quotaLimit }} {{ displayQuotaLabel }} </span>
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
                        {{
                            t('shared.file_uploader.quota_limit_reached_alert', {
                                quotaLabel: displayQuotaLabel,
                                quotaLimit,
                            })
                        }}
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
                        {{ dropZoneTitle }}
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{ dropZoneHint }}
                    </p>
                </div>

                <!-- Files list -->
                <template v-if="uploadingFiles.length > 0">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-muted-foreground text-sm">
                                <span v-if="totalPages > 0">
                                    {{
                                        t('shared.file_uploader.total_quota', {
                                            count: totalPages,
                                            label: quotaLabelForCount(totalPages),
                                        })
                                    }}
                                </span>
                                <span v-if="showQuota && wouldExceedQuota" class="text-destructive ms-2 font-medium">
                                    •
                                    {{
                                        t('shared.file_uploader.exceeds_quota', {
                                            amount: totalPages - quotaRemaining,
                                            quotaLabel: displayQuotaLabel,
                                        })
                                    }}
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
                                {{ t('shared.file_uploader.clear_failed') }}
                            </Button>
                            <Button variant="outline" size="sm" @click="onClearAllFiles" class="text-destructive hover:text-destructive/90">
                                <Trash2 class="me-2 h-4 w-4" />
                                {{ t('shared.file_uploader.clear_all') }}
                            </Button>
                        </div>
                    </div>

                    <ScrollArea v-if="uploadingFiles.length > 5" class="h-[400px] pe-4">
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
                                                    <CheckCircle class="me-1 h-3 w-3" />
                                                    {{ t('shared.file_uploader.badge_analyzed') }}
                                                </Badge>
                                                <Badge v-if="file.uploading" variant="outline" class="text-xs">
                                                    <LoaderCircle class="me-1 h-3 w-3 animate-spin" />
                                                    {{ t('shared.file_uploader.badge_analyzing') }}
                                                </Badge>
                                                <Badge v-if="file.error" variant="destructive" class="text-xs">
                                                    <AlertCircle class="me-1 h-3 w-3" />
                                                    {{ t('shared.file_uploader.badge_failed') }}
                                                </Badge>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ formatFileSize(file.file.size) }}
                                                <span v-if="formatFileLength(file)" class="ms-2"> • {{ formatFileLength(file) }} </span>
                                                <span v-if="file.uploading && file.progress > 0" class="ms-2">
                                                    • {{ t('shared.file_uploader.progress_analyzed', { progress: file.progress }) }}
                                                </span>
                                                <span v-if="file.uploading && file.uploadSpeed > 0" class="ms-2">
                                                    • {{ formatSpeed(file.uploadSpeed) }}
                                                </span>
                                                <span v-if="file.error" class="text-destructive ms-2"> • {{ file.error }} </span>
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
                                                    <span class="sr-only">{{ t('shared.file_uploader.remove_file') }}</span>
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ t('shared.file_uploader.remove_file') }}</p>
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
                                                <CheckCircle class="me-1 h-3 w-3" />
                                                {{ t('shared.file_uploader.badge_analyzed') }}
                                            </Badge>
                                            <Badge v-if="file.uploading" variant="outline" class="text-xs">
                                                <LoaderCircle class="me-1 h-3 w-3 animate-spin" />
                                                {{ t('shared.file_uploader.badge_analyzing') }}
                                            </Badge>
                                            <Badge v-if="file.error" variant="destructive" class="text-xs">
                                                <AlertCircle class="me-1 h-3 w-3" />
                                                {{ t('shared.file_uploader.badge_failed') }}
                                            </Badge>
                                        </div>
                                        <div class="text-muted-foreground text-xs">
                                            {{ formatFileSize(file.file.size) }}
                                            <span v-if="formatFileLength(file)" class="ms-2"> • {{ formatFileLength(file) }} </span>
                                            <span v-if="file.uploading && file.progress > 0" class="ms-2">
                                                • {{ t('shared.file_uploader.progress_analyzed', { progress: file.progress }) }}
                                            </span>
                                            <span v-if="file.uploading && file.uploadSpeed > 0" class="ms-2">
                                                • {{ formatSpeed(file.uploadSpeed) }}
                                            </span>
                                            <span v-if="file.error" class="text-destructive ms-2"> • {{ file.error }} </span>
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
                                                <span class="sr-only">{{ t('shared.file_uploader.remove_file') }}</span>
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ t('shared.file_uploader.remove_file') }}</p>
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
