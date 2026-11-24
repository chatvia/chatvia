/**
 * Ticking trained files and removing the ticked ones in one go: the
 * selection, "select all" over the rows loaded so far, the confirmation
 * dialog and the removal itself (only teammates with `agent.sources.delete`
 * may). A file other agents also use is only unlinked from this agent.
 */
import { removeSourceFiles } from '@/dashboard/agents/api/fileSources';
import { useSourceDeletePermission } from '@/dashboard/agents/composables/sources/useSourceDeletePermission';
import { failureReason } from '@/dashboard/agents/lib/failureReason';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { SourceFilesState } from './useSourceFiles';

export function useSourceFileSelection(agentId: string, sourceFiles: SourceFilesState) {
    const { t } = useI18n();
    const ensureCanDeleteSources = useSourceDeletePermission();

    const tickedIds = ref<Set<number>>(new Set());
    const isDialogOpen = ref(false);
    const isRemoving = ref(false);

    // A ticked file that left the list (deleted on its own row, or by a reload) no longer counts.
    const selectedFiles = computed(() => sourceFiles.processedFiles.value.filter((file) => tickedIds.value.has(file.id)));
    const selectedIds = computed(() => new Set(selectedFiles.value.map((file) => file.id)));
    const allSelected = computed(() => selectedFiles.value.length > 0 && selectedFiles.value.length === sourceFiles.processedFiles.value.length);
    const someSelected = computed(() => selectedFiles.value.length > 0 && !allSelected.value);
    const hasSharedFiles = computed(() => selectedFiles.value.some((file) => (file.agents_count ?? 0) > 1));

    function toggle(fileId: number, checked: boolean | 'indeterminate'): void {
        if (checked === true) {
            tickedIds.value.add(fileId);
        } else {
            tickedIds.value.delete(fileId);
        }
    }

    /** Ticks or unticks every row loaded so far. */
    function toggleAll(checked: boolean | 'indeterminate'): void {
        tickedIds.value = new Set(checked === true ? sourceFiles.processedFiles.value.map((file) => file.id) : []);
    }

    /** Opens the confirmation for the ticked files. */
    function confirm(): void {
        if (selectedFiles.value.length === 0 || !ensureCanDeleteSources()) return;

        isDialogOpen.value = true;
    }

    /** Removes the ticked files, then reloads the list: rows of later pages move up. */
    async function remove(): Promise<void> {
        if (selectedFiles.value.length === 0 || isRemoving.value) return;

        const count = selectedFiles.value.length;
        isRemoving.value = true;

        try {
            await removeSourceFiles(agentId, [...selectedIds.value]);
            tickedIds.value = new Set();
            toast.success(t('agents.sources.files.selected_removed', { count }, count));
        } catch (error) {
            toast.error(t('agents.sources.files.selected_remove_failed', { error: failureReason(error) }));
        } finally {
            isRemoving.value = false;
            isDialogOpen.value = false;
            // Also after a failure: a large selection goes in several requests, and some may have gone through.
            await sourceFiles.fetchFiles();
        }
    }

    return {
        selectedIds,
        selectedCount: computed(() => selectedFiles.value.length),
        allSelected,
        someSelected,
        hasSharedFiles,
        isDialogOpen,
        isRemoving,
        toggle,
        toggleAll,
        confirm,
        remove,
    };
}
