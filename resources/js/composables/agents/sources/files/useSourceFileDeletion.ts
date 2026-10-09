/**
 * Removing a file from the agent: asks for confirmation (only teammates with
 * `agent.sources.delete` may), then either unlinks a shared file from this
 * agent only or deletes it everywhere, and refreshes the quota.
 */
import { deleteSourceFile, type SourceFile, unlinkSourceFile } from '@/api/fileSources';
import { useSourceDeletePermission } from '@/composables/agents/sources/useSourceDeletePermission';
import { failureReason } from '@/lib/failureReason';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { SourceFilesState } from './useSourceFiles';

/** Keep the file in the dialog until its closing animation is over. */
const CLOSE_ANIMATION_MS = 300;

export function useSourceFileDeletion(agentId: string, sourceFiles: SourceFilesState, refreshUsage: () => Promise<void>) {
    const { t } = useI18n();
    const ensureCanDeleteSources = useSourceDeletePermission();

    const isDialogOpen = ref(false);
    const target = ref<SourceFile | null>(null);
    const isUnlinking = ref(false);
    const isDeleting = ref(false);

    function confirm(file: SourceFile): void {
        if (!ensureCanDeleteSources()) return;

        target.value = file;
        isDialogOpen.value = true;
    }

    async function finish(file: SourceFile, successMessage: string): Promise<void> {
        sourceFiles.removeFile(file.id);
        await refreshUsage();
        toast.success(successMessage);

        isDialogOpen.value = false;
        setTimeout(() => (target.value = null), CLOSE_ANIMATION_MS);
    }

    /** Removes a shared file from this agent only. */
    async function unlink(): Promise<void> {
        if (!target.value || isUnlinking.value) return;

        const file = target.value;
        isUnlinking.value = true;

        try {
            await unlinkSourceFile(agentId, file.id);
            await finish(file, t('agents.sources.files.unlinked', { name: file.name }));
        } catch (error) {
            toast.error(t('agents.sources.files.unlink_failed', { name: file.name, error: failureReason(error) }));
        } finally {
            isUnlinking.value = false;
        }
    }

    /** Deletes the file everywhere. */
    async function remove(): Promise<void> {
        if (!target.value || isDeleting.value) return;

        const file = target.value;
        isDeleting.value = true;

        try {
            await deleteSourceFile(agentId, file.id);
            await finish(file, t('agents.sources.files.deleted', { name: file.name }));
        } catch (error) {
            toast.error(t('agents.sources.files.delete_failed', { name: file.name, error: failureReason(error) }));
        } finally {
            isDeleting.value = false;
        }
    }

    return { isDialogOpen, target, isUnlinking, isDeleting, confirm, unlink, remove };
}
