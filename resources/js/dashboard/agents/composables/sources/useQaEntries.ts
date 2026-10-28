/**
 * The agent's Q&A entries on the Q&A page: loads the list, reads one entry for
 * the edit form, and deletes an entry behind a confirmation dialog (which stays
 * open with a spinner until the request is done).
 */
import { deleteQaEntry, fetchQaEntries, fetchQaEntry, type QaEntry } from '@/dashboard/agents/api/qaSources';
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useQaEntries(agentId: string) {
    const { t } = useI18n();
    const { hasPermission } = useTeamPermissions();

    const entries = ref<QaEntry[]>([]);
    const isLoading = ref(false);

    const isDeleteDialogOpen = ref(false);
    const entryToDelete = ref<QaEntry | null>(null);
    const isDeleting = ref(false);

    async function fetchEntries(): Promise<void> {
        isLoading.value = true;
        try {
            entries.value = await fetchQaEntries(agentId);
        } catch {
            toast.error(t('agents.sources.qa.load_entries_failed'));
        } finally {
            isLoading.value = false;
        }
    }

    /** The entry as the edit form needs it, or null when it could not be loaded (the user gets a toast). */
    async function fetchEntry(entry: QaEntry): Promise<QaEntry | null> {
        isLoading.value = true;
        try {
            return await fetchQaEntry(agentId, entry.id);
        } catch {
            toast.error(t('agents.sources.qa.load_failed'));

            return null;
        } finally {
            isLoading.value = false;
        }
    }

    function confirmDelete(entry: QaEntry): void {
        if (!hasPermission('agent.sources.delete')) {
            toast.error(t('agents.sources.common.permission_denied'), {
                description: t('agents.sources.common.permission_denied_description'),
            });
            return;
        }

        entryToDelete.value = entry;
        isDeleteDialogOpen.value = true;
    }

    async function deleteEntry(): Promise<void> {
        if (!entryToDelete.value) {
            return;
        }

        try {
            isDeleting.value = true;
            await deleteQaEntry(agentId, entryToDelete.value.id);
            toast.success(t('agents.sources.qa.deleted'));
            fetchEntries();
        } catch {
            toast.error(t('agents.sources.qa.delete_failed'));
        } finally {
            isDeleting.value = false;
            isDeleteDialogOpen.value = false;
            entryToDelete.value = null;
        }
    }

    return { entries, isLoading, fetchEntries, fetchEntry, isDeleteDialogOpen, isDeleting, confirmDelete, deleteEntry };
}
