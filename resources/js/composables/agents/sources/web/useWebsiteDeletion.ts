/**
 * Removing a website from the agent: asks for confirmation (only teammates
 * with `agent.sources.delete` may), then either unlinks a shared website from
 * this agent only or deletes it everywhere.
 */
import { errorMessage } from '@/api/http';
import { deleteWebsite, unlinkWebsite } from '@/api/webSources';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { failureReason } from '@/lib/failureReason';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { WebSource, WebSourcesState } from './useWebSources';
import type { WebsiteTraining } from './useWebsiteTraining';

export function useWebsiteDeletion(agentId: string, sources: WebSourcesState, training: WebsiteTraining) {
    const { t } = useI18n();
    const { hasPermission } = useTeamPermissions();

    const isDialogOpen = ref(false);
    const target = ref<WebSource | null>(null);
    const isUnlinking = ref(false);
    const isDeleting = ref(false);

    /** The dialog text for a website that is not shared: it differs while the website is still being processed. */
    const description = computed(() =>
        target.value?.status === 'ingesting' || target.value?.status === 'crawling'
            ? t('agents.sources.web.delete_training_description')
            : t('agents.sources.web.delete_description'),
    );

    function confirm(source: WebSource): void {
        if (!hasPermission('agent.sources.delete')) {
            toast.error(t('agents.sources.common.permission_denied'), {
                description: t('agents.sources.common.permission_denied_description'),
            });
            return;
        }

        target.value = source;
        isDialogOpen.value = true;
    }

    /** Removes the website from this agent only; the dialog stays open if it fails. */
    async function unlink(): Promise<void> {
        if (!target.value || isUnlinking.value) return;

        const website = target.value;
        isUnlinking.value = true;

        try {
            await unlinkWebsite(agentId, website.id);
            toast.success(t('agents.sources.web.unlinked'));

            isDialogOpen.value = false;
            target.value = null;

            await sources.fetchWebSources();
        } catch (error) {
            toast.error(t('agents.sources.web.unlink_failed', { error: failureReason(error) }));
        } finally {
            isUnlinking.value = false;
        }
    }

    /** Deletes the website everywhere; the dialog closes either way. */
    async function remove(): Promise<void> {
        if (!target.value || isDeleting.value) return;

        const website = target.value;
        isDeleting.value = true;

        try {
            await deleteWebsite(agentId, website.id);
            sources.removeSource(website.id);
            training.deselect(website.id);
            toast.success(t('agents.sources.web.deleted'));
        } catch (error) {
            toast.error(errorMessage(error, t('agents.sources.web.delete_failed')));
        } finally {
            isDeleting.value = false;
        }

        target.value = null;
        isDialogOpen.value = false;
    }

    return { isDialogOpen, target, description, isUnlinking, isDeleting, confirm, unlink, remove };
}
