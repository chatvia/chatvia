<script setup lang="ts">
/**
 * One file of the agent's knowledge base: name, type, status icon, size,
 * length and date, a progress bar while it trains, and buttons to view its
 * summary (once trained), to replace it with a new version and to remove it.
 */
import type { SourceFile } from '@/api/fileSources';
import IngestionProgressBar from '@/components/common/IngestionProgressBar.vue';
import SourceActionButton from '@/components/agents/sources/SourceActionButton.vue';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFileFormatting } from '@/composables/agents/sources/files/useFileFormatting';
import { useDateFormat } from '@/composables/useDateFormat';
import { CheckCircle, FileText, FileUp, Info, LoaderCircle, Trash2, XCircle } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    file: SourceFile;
    /** Uploaded files are being trained; row actions wait until that is done. */
    isBusy: boolean;
}>();

const emit = defineEmits<{
    summary: [file: SourceFile];
    replace: [file: SourceFile];
    remove: [file: SourceFile];
}>();

const { t } = useI18n();
const { formatDate } = useDateFormat();
const { formatFileType, formatFileLength } = useFileFormatting();

const STATUS_ICONS: Record<string, { icon: Component; class: string; labelKey: string }> = {
    ingesting: { icon: LoaderCircle, class: 'animate-spin text-blue-500', labelKey: 'agents.sources.files.status_processing' },
    ingested: { icon: CheckCircle, class: 'text-green-500', labelKey: 'agents.sources.files.status_ready' },
    failed: { icon: XCircle, class: 'text-red-500', labelKey: 'agents.sources.files.status_failed' },
};

const statusIcon = computed(() => STATUS_ICONS[props.file.status]);
const isShared = computed(() => (props.file.agents_count ?? 0) > 1);
</script>

<template>
    <div class="flex items-center justify-between rounded-lg border p-3">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <FileText class="text-muted-foreground h-5 w-5 flex-shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center gap-2">
                    <span class="truncate font-medium">{{ file.name }}</span>
                    <Badge variant="outline" class="flex-shrink-0 text-xs">{{ formatFileType(file.mime_type) }}</Badge>
                    <TooltipProvider v-if="statusIcon">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div class="flex items-center">
                                    <component :is="statusIcon.icon" :class="['h-4 w-4 flex-shrink-0', statusIcon.class]" />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ t(statusIcon.labelKey) }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
                <div class="text-muted-foreground text-xs">
                    {{ t('agents.sources.files.size_kb', { size: file.size_in_kb.toFixed(1) }) }}
                    <span v-if="formatFileLength(file)" class="ms-2"> • {{ formatFileLength(file) }} </span>
                    • {{ t('agents.sources.files.added_at', { date: formatDate(file.created_at) }) }}
                </div>
                <IngestionProgressBar v-if="file.status === 'ingesting'" :progress="file.progress ?? 0" />
            </div>
        </div>
        <div class="flex items-center gap-1">
            <SourceActionButton
                v-if="file.status === 'ingested'"
                :icon="Info"
                :label="t('agents.sources.files.view_summary')"
                :disabled="isBusy"
                class="h-8 w-8 flex-shrink-0 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                @click="emit('summary', file)"
            />
            <SourceActionButton
                v-if="file.status !== 'ingesting'"
                :icon="FileUp"
                :label="t('agents.sources.files.replace_file')"
                :disabled="isBusy"
                class="h-8 w-8 flex-shrink-0 p-0"
                @click="emit('replace', file)"
            />
            <SourceActionButton
                :icon="Trash2"
                :label="t('agents.sources.files.remove_file')"
                :disabled="isBusy"
                class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                @click="emit('remove', file)"
            >
                <template #tooltip>
                    <div class="text-xs">
                        <p v-if="isShared">
                            {{ t('agents.sources.common.remove_from_agent') }}<br />
                            <span class="text-muted-foreground">{{ t('agents.sources.common.used_in_agents', { count: file.agents_count }) }}</span>
                        </p>
                        <p v-else>{{ t('agents.sources.common.delete_from_knowledge_base') }}</p>
                    </div>
                </template>
            </SourceActionButton>
        </div>
    </div>
</template>
