/**
 * The "ready to train" step of the files page: starting training for every
 * uploaded file, or clearing them all (the uploaded files on the server and
 * whatever is still in the uploader).
 */
import { deleteUploadedFiles, trainUploadedFiles } from '@/api/fileSources';
import { useSourceDeletePermission } from '@/composables/agents/sources/useSourceDeletePermission';
import { failureReason } from '@/lib/failureReason';
import { ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { SourceFilesState } from './useSourceFiles';

/** The parts of the FileUploader component this step drives. */
export interface UploaderHandle {
    totalFiles: number;
    clearAllFiles: () => void;
    removeFileById: (fileId: string) => void;
}

export function useUploadedFiles(agentId: string, sourceFiles: SourceFilesState, uploader: Ref<UploaderHandle | null>) {
    const { t } = useI18n();
    const ensureCanDeleteSources = useSourceDeletePermission();

    const isTraining = ref(false);
    const isClearDialogOpen = ref(false);

    const reload = async (): Promise<void> => {
        await sourceFiles.fetchFiles();
    };

    /** Trains every uploaded file in one request; reloads either way, as some files may have started. */
    async function trainAll(): Promise<void> {
        if (sourceFiles.uploadedFiles.value.length === 0) return;

        isTraining.value = true;
        try {
            await trainUploadedFiles(agentId);
            await reload();
        } catch (error) {
            toast.error(t('agents.sources.files.process_failed', { error: failureReason(error) }));
            await reload();
        } finally {
            isTraining.value = false;
        }
    }

    /** Opens the "clear all" confirmation when there is anything to clear. */
    function confirmClearAll(): void {
        if (!ensureCanDeleteSources()) return;

        const hasUploadingFiles = (uploader.value?.totalFiles ?? 0) > 0;
        if (hasUploadingFiles || sourceFiles.uploadedFiles.value.length > 0) {
            isClearDialogOpen.value = true;
        }
    }

    async function clearAll(): Promise<void> {
        try {
            if (sourceFiles.uploadedFiles.value.length > 0) {
                await deleteUploadedFiles(agentId);
            }

            uploader.value?.clearAllFiles();
            sourceFiles.removeUploadedFiles();
            await reload();
        } catch (error) {
            // The uploader is local, so clear it even when the server part failed.
            uploader.value?.clearAllFiles();
            toast.error(t('agents.sources.files.clear_failed', { error: failureReason(error) }));
        }

        isClearDialogOpen.value = false;
    }

    return { isTraining, isClearDialogOpen, trainAll, confirmClearAll, clearAll };
}
