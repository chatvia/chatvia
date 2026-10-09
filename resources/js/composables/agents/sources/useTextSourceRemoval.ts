/**
 * Removing a text from the agent: the confirmation dialog's state and its two
 * outcomes, unlink (this agent only, for a text other agents share) or delete
 * (for every agent). `onRemoved` runs after either succeeds, to reload the list.
 */
import { errorMessage } from '@/api/http';
import { deleteTextSource, unlinkTextSource, type TextSource } from '@/api/textSources';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useTextSourceRemoval(agentId: string, onRemoved: () => Promise<void>) {
    const { t } = useI18n();
    const { hasPermission } = useTeamPermissions();

    const isDialogOpen = ref(false);
    const target = ref<TextSource | null>(null);
    const isUnlinking = ref(false);
    const isDeleting = ref(false);

    /** The server's message, else the request error's own text. */
    const failureReason = (error: unknown): string => errorMessage(error, error instanceof Error ? error.message : '');

    function confirmRemove(entry: TextSource): void {
        if (!hasPermission('agent.sources.delete')) {
            toast.error(t('agents.sources.common.permission_denied'), {
                description: t('agents.sources.common.permission_denied_description'),
            });
            return;
        }

        target.value = entry;
        isDialogOpen.value = true;
    }

    function closeDialog(): void {
        isDialogOpen.value = false;
        target.value = null;
    }

    async function unlink(): Promise<void> {
        if (!target.value || isUnlinking.value) {
            return;
        }

        const text = target.value;
        isUnlinking.value = true;

        try {
            await unlinkTextSource(agentId, text.id);
            toast.success(t('agents.sources.text.unlinked'));
            closeDialog();
            await onRemoved();
        } catch (error) {
            toast.error(t('agents.sources.text.unlink_failed', { error: failureReason(error) }));
        } finally {
            isUnlinking.value = false;
        }
    }

    async function remove(): Promise<void> {
        if (!target.value || isDeleting.value) {
            return;
        }

        const text = target.value;
        isDeleting.value = true;

        try {
            await deleteTextSource(agentId, text.id);
            toast.success(t('agents.sources.text.deleted'));
            closeDialog();
            await onRemoved();
        } catch (error) {
            toast.error(t('agents.sources.text.delete_failed', { error: failureReason(error) }));
        } finally {
            isDeleting.value = false;
        }
    }

    return { isDialogOpen, target, isUnlinking, isDeleting, confirmRemove, unlink, remove };
}
