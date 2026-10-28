<script setup lang="ts">
/**
 * Knowledge-base file picker: drop zone, file counter, and the list
 * of picked files with their analysis state. The state lives in
 * `useFileUploader`; the parent reads the files through the exposed API.
 */
import UploadingFileRow from '@/dashboard/agents/components/file-uploader/UploadingFileRow.vue';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { ScrollArea } from '@/shared/ui/scroll-area';
import {
    useFileUploader,
    type FileAnalyzedData,
    type FileUploaderConfig,
    type UploadingFileData,
} from '@/dashboard/agents/composables/useFileUploader';
import { Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    title?: string;
    description?: string;
    config: FileUploaderConfig;
    /** What a file's length counts: minutes (audio, video) or pages (everything else). */
    lengthUnit?: 'pages' | 'minutes';
    disabled?: boolean;
    disabledMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    lengthUnit: 'pages',
    disabled: false,
});

const { t } = useI18n();

const displayTitle = computed(() => props.title ?? t('shared.file_uploader.title_default'));
const displayDescription = computed(() => props.description ?? t('shared.file_uploader.description_default'));

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

/** Total length of the picked files: "12 pages" or "3 minutes". */
const totalLengthLabel = computed(() =>
    props.lengthUnit === 'minutes'
        ? t('shared.file_uploader.minute_length', { count: totalPages.value }, { plural: totalPages.value })
        : t('shared.file_uploader.page_length', { count: totalPages.value }, { plural: totalPages.value }),
);

// UI state
const canUpload = computed(() => {
    return !props.disabled && totalFiles.value < props.config.maxFiles;
});

// Only the file limit can block an enabled uploader.
const fileLimitReached = computed(() => !props.disabled && totalFiles.value >= props.config.maxFiles);

const dropZoneTitle = computed(() =>
    fileLimitReached.value ? t('shared.file_uploader.drop_zone_file_limit_reached') : t('shared.file_uploader.drop_zone_select_files'),
);

const dropZoneHint = computed(() =>
    fileLimitReached.value
        ? t('shared.file_uploader.drop_zone_max_files_hint', { max: props.config.maxFiles })
        : t('shared.file_uploader.drop_zone_default_hint'),
);

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
                <!-- File count -->
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-sm">
                        {{ t('shared.file_uploader.file_count', { current: totalFiles, max: config.maxFiles }) }}
                    </span>
                </div>

                <!-- File input -->
                <input ref="fileInputRef" :accept="config.acceptedTypes" class="hidden" multiple type="file" @change="handleFileUpload" />

                <!-- Drop zone -->
                <div
                    class="hover:bg-muted/50 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center"
                    :class="{
                        'cursor-not-allowed opacity-50': disabled,
                        'cursor-not-allowed': !canUpload && !disabled,
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
                                <span v-if="totalPages > 0">{{ totalLengthLabel }}</span>
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
                            <UploadingFileRow
                                v-for="file in uploadingFiles"
                                :key="file.id"
                                :file="file"
                                :type-label="formatFileType(file.stats?.mime_type || file.file.type)"
                                :size-label="formatFileSize(file.file.size)"
                                :speed-label="formatSpeed(file.uploadSpeed)"
                                @remove="onRemoveFile"
                            />
                        </div>
                    </ScrollArea>
                    <div v-else class="space-y-2">
                        <UploadingFileRow
                            v-for="file in uploadingFiles"
                            :key="file.id"
                            :file="file"
                            :type-label="formatFileType(file.stats?.mime_type || file.file.type)"
                            :size-label="formatFileSize(file.file.size)"
                            :speed-label="formatSpeed(file.uploadSpeed)"
                            @remove="onRemoveFile"
                        />
                    </div>
                </template>
            </div>
        </CardContent>
    </Card>
</template>
