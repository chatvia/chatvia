/**
 * The agent's files of one kind, split into "uploaded" (waiting for training)
 * and the rest (training, trained or failed). A reload never moves a file the
 * page already saw finish back to "training".
 */
import { listSourceFiles, type SourceFile } from '@/api/fileSources';
import { computed, ref } from 'vue';

export function useSourceFiles(agentId: string, fileType?: string) {
    const files = ref<SourceFile[]>([]);
    const isLoading = ref(false);
    const isInitialLoad = ref(true);

    const uploadedFiles = computed(() => files.value.filter((file) => file.status === 'uploaded'));
    const processedFiles = computed(() => files.value.filter((file) => file.status !== 'uploaded'));

    async function fetchFiles(): Promise<void> {
        isLoading.value = true;

        try {
            const fresh = await listSourceFiles(agentId, fileType);

            // A reload can race ahead of the training batch's final commit and
            // still report a just-finished file as 'ingesting' (at 99 %). Keep
            // every file this page already knows is 'ingested'.
            const ingestedIds = new Set(files.value.filter((file) => file.status === 'ingested').map((file) => file.id));

            files.value = fresh.map((file) =>
                file.status === 'ingesting' && ingestedIds.has(file.id) ? { ...file, status: 'ingested', progress: 100 } : file,
            );
        } catch {
            // The list keeps its last state; the next reload or broadcast fixes it.
        } finally {
            isLoading.value = false;
            isInitialLoad.value = false;
        }
    }

    function removeFile(fileId: number): void {
        files.value = files.value.filter((file) => file.id !== fileId);
    }

    /** Drops the uploaded (untrained) files from the list after they were deleted on the server. */
    function removeUploadedFiles(): void {
        files.value = processedFiles.value;
    }

    /** Applies a training-progress broadcast to a file. */
    function setProgress(fileId: number, progress: number): void {
        files.value = files.value.map((file) =>
            file.id === fileId ? { ...file, progress, status: progress >= 100 ? 'ingested' : 'ingesting' } : file,
        );
    }

    function findFile(fileId: number): SourceFile | undefined {
        return files.value.find((file) => file.id === fileId);
    }

    return { files, uploadedFiles, processedFiles, isLoading, isInitialLoad, fetchFiles, removeFile, removeUploadedFiles, setProgress, findFile };
}

export type SourceFilesState = ReturnType<typeof useSourceFiles>;
