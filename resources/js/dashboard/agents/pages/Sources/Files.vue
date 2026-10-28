<script setup lang="ts">
/**
 * File sources of an agent (all documents, or only one kind: PDF, Word,
 * Markdown, audio, video). Files are uploaded first, wait in "ready to train",
 * then train; training progress arrives live over Reverb. A trained file can
 * be replaced by a new version, which trains again. Audio and video are
 * measured in minutes, everything else in pages. Both lists load one page at
 * a time.
 */
import { replaceSourceFile, type SourceFile } from '@/dashboard/agents/api/fileSources';
import { errorMessage } from '@/shared/api/http';
import FileUploader from '@/dashboard/agents/components/FileUploader.vue';
import AttachFileDialog from '@/dashboard/agents/components/AttachFileDialog.vue';
import DeleteConfirmationDialog from '@/dashboard/components/DeleteConfirmationDialog.vue';
import DeleteSourceDialog from '@/dashboard/agents/components/sources/DeleteSourceDialog.vue';
import FileSummaryDialog from '@/dashboard/agents/components/sources/files/FileSummaryDialog.vue';
import TrainedFilesCard from '@/dashboard/agents/components/sources/files/TrainedFilesCard.vue';
import UploadedFilesCard from '@/dashboard/agents/components/sources/files/UploadedFilesCard.vue';
import { isMeasuredInMinutes } from '@/dashboard/agents/composables/sources/files/useFileFormatting';
import { useFileIngestionProgress } from '@/dashboard/agents/composables/sources/files/useFileIngestionProgress';
import { useFileSummary } from '@/dashboard/agents/composables/sources/files/useFileSummary';
import { useFileUploadConfig } from '@/dashboard/agents/composables/sources/files/useFileUploadConfig';
import { useSourceFileDeletion } from '@/dashboard/agents/composables/sources/files/useSourceFileDeletion';
import { useSourceFiles } from '@/dashboard/agents/composables/sources/files/useSourceFiles';
import { useUploadedFiles } from '@/dashboard/agents/composables/sources/files/useUploadedFiles';
import type { UploadingFileData } from '@/dashboard/agents/composables/useFileUploader';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    /** Limit the page to one kind of file: 'pdf', 'word', 'markdown', 'audio' or 'video'. */
    fileType?: string;
}>();

const { t } = useI18n();
const agentId = props.agent.external_id;

const fileUploaderRef = ref<InstanceType<typeof FileUploader> | null>(null);
const uploadingFiles = ref<UploadingFileData[]>([]);

const sourceFiles = useSourceFiles(agentId, props.fileType);
const {
    uploadedFiles,
    processedFiles,
    uploadedTotal,
    processedTotal,
    hasMoreUploaded,
    hasMoreProcessed,
    isLoadingMoreUploaded,
    isLoadingMoreProcessed,
    isLoading,
    isInitialLoad,
    fetchFiles,
} = sourceFiles;
useFileIngestionProgress(agentId, sourceFiles);

/** Minutes / pages of everything waiting for training: uploaded plus still uploading. */
const pendingLength = (inMinutes: boolean): number =>
    uploadedFiles.value.reduce((total, file) => (isMeasuredInMinutes(file) === inMinutes ? total + (file.length || 0) : total), 0) +
    uploadingFiles.value.reduce((total, file) => (isMeasuredInMinutes(file.stats) === inMinutes ? total + (file.stats?.length || 0) : total), 0);
const pendingMinutes = computed(() => pendingLength(true));
const pendingPages = computed(() => pendingLength(false));

// A finished upload leaves the uploader and shows up under "ready to train".
const {
    config: uploadConfig,
    title: uploadTitle,
    description: uploadDescription,
    acceptsFiles,
} = useFileUploadConfig(agentId, props.fileType, (file) => {
    fileUploaderRef.value?.removeFileById(file.id);
    sourceFiles.fetchUploadedFiles();
});

const { isTraining, isClearDialogOpen, trainAll, confirmClearAll, clearAll } = useUploadedFiles(agentId, sourceFiles, fileUploaderRef);
const deletion = useSourceFileDeletion(agentId, sourceFiles);
const { isDialogOpen: isDeleteDialogOpen, target: fileToDelete, isUnlinking, isDeleting } = deletion;
const summary = useFileSummary(agentId);
const { isOpen: isSummaryOpen, file: summaryFile, summary: summaryData, isLoading: isSummaryLoading } = summary;

const isAttachDialogOpen = ref(false);

// Files that were only uploaded belong to this agent alone, so they are only ever deleted, never unlinked.
const confirmUploadedRemoval = (file: SourceFile) => deletion.confirm({ ...file, agents_count: 0 });

// "Replace file": a hidden file picker for the row it was opened from.
const replaceInput = ref<HTMLInputElement | null>(null);
const replacing = ref<SourceFile | null>(null);

function pickReplacement(file: SourceFile) {
    replacing.value = file;
    replaceInput.value?.click();
}

async function onReplacementPicked(event: Event) {
    const input = event.target as HTMLInputElement;
    const picked = input.files?.[0];
    const target = replacing.value;
    input.value = '';

    if (!picked || !target) return;

    try {
        await replaceSourceFile(agentId, target.id, picked);
        toast.success(t('agents.sources.files.replace_started', { name: picked.name }));
        fetchFiles();
    } catch (error) {
        toast.error(errorMessage(error, t('agents.sources.files.replace_failed')));
    } finally {
        replacing.value = null;
    }
}

onMounted(() => {
    fetchFiles();
});

defineExpose({ refreshFiles: fetchFiles });
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <FileUploader
                ref="fileUploaderRef"
                :config="uploadConfig"
                :title="uploadTitle"
                :description="uploadDescription"
                :length-unit="fileType === 'audio' || fileType === 'video' ? 'minutes' : 'pages'"
                :disabled="isTraining || !acceptsFiles"
                :disabled-message="t('agents.sources.files.uploads_disabled')"
                @files-changed="(files) => (uploadingFiles = files)"
            />

            <UploadedFilesCard
                v-if="uploadedFiles.length > 0"
                :files="uploadedFiles"
                :total="uploadedTotal"
                :has-more="hasMoreUploaded"
                :is-loading-more="isLoadingMoreUploaded"
                :total-pages="pendingPages"
                :total-minutes="pendingMinutes"
                :is-training="isTraining"
                @load-more="sourceFiles.loadMoreUploaded"
                @clear-all="confirmClearAll"
                @train="trainAll"
                @remove="confirmUploadedRemoval"
            />

            <TrainedFilesCard
                :files="processedFiles"
                :total="processedTotal"
                :has-more="hasMoreProcessed"
                :is-loading-more="isLoadingMoreProcessed"
                :is-loading="isLoading && isInitialLoad"
                :is-busy="isTraining"
                @attach="isAttachDialogOpen = true"
                @load-more="sourceFiles.loadMoreTrained"
                @summary="summary.show"
                @replace="pickReplacement"
                @remove="deletion.confirm"
            />

            <input ref="replaceInput" type="file" class="hidden" @change="onReplacementPicked" />

            <DeleteSourceDialog
                v-if="fileToDelete"
                v-model:open="isDeleteDialogOpen"
                :title="fileToDelete.name"
                :agents-count="fileToDelete.agents_count"
                :description="t('agents.sources.files.delete_description')"
                :shared-prefix="t('agents.sources.files.delete_shared_prefix')"
                :shared-suffix="t('agents.sources.files.delete_shared_suffix')"
                :is-unlinking="isUnlinking"
                :is-deleting="isDeleting"
                @unlink="deletion.unlink"
                @delete="deletion.remove"
            />

            <DeleteConfirmationDialog
                v-model:open="isClearDialogOpen"
                :title="t('agents.sources.files.clear_all_title')"
                :description="t('agents.sources.files.clear_all_description')"
                :confirm-text="t('agents.sources.files.clear_all_confirm')"
                :cancel-text="t('agents.sources.common.cancel')"
                @confirm="clearAll"
            />

            <FileSummaryDialog v-model:open="isSummaryOpen" :file-name="summaryFile?.name" :summary="summaryData" :is-loading="isSummaryLoading" />

            <AttachFileDialog v-model:open="isAttachDialogOpen" :agent-id="agentId" :file-type="fileType" @file-attached="fetchFiles" />
        </div>
    </div>
</template>
