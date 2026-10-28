/**
 * Guard for the delete buttons on the knowledge source pages: only teammates
 * with the `agent.sources.delete` permission may remove sources. The returned
 * check tells the user with a toast when they may not.
 */
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useSourceDeletePermission() {
    const { t } = useI18n();
    const { hasPermission } = useTeamPermissions();

    return function ensureCanDeleteSources(): boolean {
        if (hasPermission('agent.sources.delete')) {
            return true;
        }

        toast.error(t('agents.sources.common.permission_denied'), {
            description: t('agents.sources.common.permission_denied_description'),
        });

        return false;
    };
}
