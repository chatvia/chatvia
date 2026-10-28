<script setup lang="ts">
/**
 * One file in the knowledge-base file picker: name, analysis badges, size,
 * pages/minutes, upload progress and a remove button.
 */
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import type { UploadingFileData } from '@/dashboard/agents/composables/useFileUploader';
import { AlertCircle, CheckCircle, File, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    file: UploadingFileData;
    /** Short type label, e.g. "PDF". */
    typeLabel: string;
    /** Human file size, e.g. "1.2 MB". */
    sizeLabel: string;
    /** Human upload speed, e.g. "350.0 KB/s". */
    speedLabel: string;
}>();

const emit = defineEmits<{ remove: [fileId: string] }>();

const { t } = useI18n();

// Pages for documents, minutes for audio — empty until the server has analysed the file.
const lengthLabel = computed((): string => {
    const length = props.file.stats?.length;
    if (!length) return '';

    if (props.file.stats?.is_audio_file) {
        return t('shared.file_uploader.minute_length', { count: length }, { plural: length });
    }

    return t('shared.file_uploader.page_length', { count: length }, { plural: length });
});
</script>

<template>
    <div class="flex items-center justify-between rounded-lg border p-3">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <File class="text-muted-foreground h-5 w-5 flex-shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center gap-2">
                    <span class="truncate font-medium">{{ file.file.name }}</span>
                    <Badge v-if="file.isAnalyzed" variant="secondary" class="text-xs">
                        {{ typeLabel }}
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
                    {{ sizeLabel }}
                    <span v-if="lengthLabel" class="ms-2"> • {{ lengthLabel }} </span>
                    <span v-if="file.uploading && file.progress > 0" class="ms-2">
                        • {{ t('shared.file_uploader.progress_analyzed', { progress: file.progress }) }}
                    </span>
                    <span v-if="file.uploading && file.uploadSpeed > 0" class="ms-2"> • {{ speedLabel }} </span>
                    <span v-if="file.error" class="text-destructive ms-2"> • {{ file.error }} </span>
                </div>
                <!-- Progress bar -->
                <div v-if="file.uploading && file.progress > 0" class="mt-2">
                    <div class="bg-muted h-1.5 w-full rounded-full">
                        <div class="bg-primary h-1.5 rounded-full transition-all duration-300" :style="{ width: `${file.progress}%` }"></div>
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
                        @click="emit('remove', file.id)"
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
