/**
 * The agent's files of one kind, as two lists the server sends one page at a
 * time: "uploaded" (waiting for training) and the rest (training, trained or
 * failed). A reload never moves a file the page already saw finish back to
 * "training".
 */
import { listSourceFiles, type SourceFile } from '@/api/fileSources';
import { usePagedSourceList } from '@/composables/agents/sources/usePagedSourceList';
import { computed, ref } from 'vue';

export function useSourceFiles(agentId: string, fileType?: string) {
    const uploaded = usePagedSourceList<SourceFile>((page) => listSourceFiles(agentId, 'uploaded', page, fileType));
    const trained = usePagedSourceList<SourceFile>((page) => listSourceFiles(agentId, 'trained', page, fileType));
    const isInitialLoad = ref(true);

    const uploadedFiles = uploaded.items;
    const processedFiles = trained.items;
    const isLoading = computed(() => uploaded.isLoading.value || trained.isLoading.value);

    async function fetchFiles(): Promise<void> {
        // A reload can race ahead of the training batch's final commit and
        // still report a just-finished file as 'ingesting' (at 99 %). Keep
        // every file this page already knows is 'ingested'.
        const ingestedIds = new Set(processedFiles.value.filter((file) => file.status === 'ingested').map((file) => file.id));

        try {
            await Promise.all([uploaded.reload(), trained.reload()]);

            processedFiles.value = processedFiles.value.map((file) =>
                file.status === 'ingesting' && ingestedIds.has(file.id) ? { ...file, status: 'ingested', progress: 100 } : file,
            );
        } catch {
            // The lists keep their last state; the next reload or broadcast fixes them.
        } finally {
            isInitialLoad.value = false;
        }
    }

    /** Reloads only the files waiting for training (a new upload lands there). */
    async function fetchUploadedFiles(): Promise<void> {
        await uploaded.reload().catch(() => undefined);
    }

    /** Adds the next page of uploaded files. A failure keeps the list as it is. */
    async function loadMoreUploaded(): Promise<void> {
        await uploaded.loadMore().catch(() => undefined);
    }

    /** Adds the next page of trained files. A failure keeps the list as it is. */
    async function loadMoreTrained(): Promise<void> {
        await trained.loadMore().catch(() => undefined);
    }

    function removeFile(fileId: number): void {
        uploaded.remove(fileId);
        trained.remove(fileId);
    }

    /** Drops the uploaded (untrained) files from the list after they were deleted on the server. */
    function removeUploadedFiles(): void {
        uploaded.clear();
    }

    /** Applies a training-progress broadcast to a file. */
    function setProgress(fileId: number, progress: number): void {
        processedFiles.value = processedFiles.value.map((file) =>
            file.id === fileId ? { ...file, progress, status: progress >= 100 ? 'ingested' : 'ingesting' } : file,
        );
    }

    function findFile(fileId: number): SourceFile | undefined {
        return processedFiles.value.find((file) => file.id === fileId) ?? uploadedFiles.value.find((file) => file.id === fileId);
    }

    return {
        uploadedFiles,
        processedFiles,
        uploadedTotal: uploaded.total,
        processedTotal: trained.total,
        hasMoreUploaded: uploaded.hasMore,
        hasMoreProcessed: trained.hasMore,
        isLoadingMoreUploaded: uploaded.isLoadingMore,
        isLoadingMoreProcessed: trained.isLoadingMore,
        isLoading,
        isInitialLoad,
        fetchFiles,
        fetchUploadedFiles,
        loadMoreUploaded,
        loadMoreTrained,
        removeFile,
        removeUploadedFiles,
        setProgress,
        findFile,
    };
}

export type SourceFilesState = ReturnType<typeof useSourceFiles>;
