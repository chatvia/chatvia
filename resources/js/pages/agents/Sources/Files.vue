<script setup lang="ts">
/**
 * File sources of an agent (all documents, or only one kind: PDF, Word,
 * Markdown, audio, video). Files are uploaded first, wait in "ready to train",
 * then train; training progress arrives live over Reverb. Audio and video are
 * measured in minutes, everything else in pages.
 */
import type { SourceFile } from '@/api/fileSources';
import FileUploader from '@/components/FileUploader.vue';
import AttachFileDialog from '@/components/common/AttachFileDialog.vue';
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import DeleteSourceDialog from '@/components/agents/sources/DeleteSourceDialog.vue';
import FileSummaryDialog from '@/components/agents/sources/files/FileSummaryDialog.vue';
import TrainedFilesCard from '@/components/agents/sources/files/TrainedFilesCard.vue';
import UploadedFilesCard from '@/components/agents/sources/files/UploadedFilesCard.vue';
import { isMeasuredInMinutes } from '@/composables/agents/sources/files/useFileFormatting';
import { useFileIngestionProgress } from '@/composables/agents/sources/files/useFileIngestionProgress';
import { useFileSummary } from '@/composables/agents/sources/files/useFileSummary';
import { useFileUploadConfig } from '@/composables/agents/sources/files/useFileUploadConfig';
import { useSourceFileDeletion } from '@/composables/agents/sources/files/useSourceFileDeletion';
import { useSourceFiles } from '@/composables/agents/sources/files/useSourceFiles';
import { useUploadedFiles } from '@/composables/agents/sources/files/useUploadedFiles';
import type { UploadingFileData } from '@/composables/useFileUploader';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

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
const { uploadedFiles, processedFiles, isLoading, isInitialLoad, fetchFiles } = sourceFiles;
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
} = useFileUploadConfig(agentId, props.fileType, (file) => {
    fileUploaderRef.value?.removeFileById(file.id);
    fetchFiles();
});

const { isTraining, isClearDialogOpen, trainAll, confirmClearAll, clearAll } = useUploadedFiles(agentId, sourceFiles, fileUploaderRef);
const deletion = useSourceFileDeletion(agentId, sourceFiles);
const { isDialogOpen: isDeleteDialogOpen, target: fileToDelete, isUnlinking, isDeleting } = deletion;
const summary = useFileSummary(agentId);
const { isOpen: isSummaryOpen, file: summaryFile, summary: summaryData, isLoading: isSummaryLoading } = summary;

const isAttachDialogOpen = ref(false);

// Files that were only uploaded belong to this agent alone, so they are only ever deleted, never unlinked.
const confirmUploadedRemoval = (file: SourceFile) => deletion.confirm({ ...file, agents_count: 0 });

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
                :disabled="isTraining"
                :disabled-message="t('agents.sources.files.uploads_disabled')"
                @files-changed="(files) => (uploadingFiles = files)"
            />

            <UploadedFilesCard
                v-if="uploadedFiles.length > 0"
                :files="uploadedFiles"
                :total-pages="pendingPages"
                :total-minutes="pendingMinutes"
                :is-training="isTraining"
                @clear-all="confirmClearAll"
                @train="trainAll"
                @remove="confirmUploadedRemoval"
            />

            <TrainedFilesCard
                :files="processedFiles"
                :is-loading="isLoading && isInitialLoad"
                :is-busy="isTraining"
                @attach="isAttachDialogOpen = true"
                @summary="summary.show"
                @remove="deletion.confirm"
            />

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
