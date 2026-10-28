/**
 * How often a trained website is crawled and trained again on its own. The
 * row shows the new choice at once; it goes back if saving fails.
 */
import { setWebsiteRefresh, type WebsiteRefreshInterval } from '@/dashboard/agents/api/webSources';
import { errorMessage } from '@/shared/api/http';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { WebSource, WebSourcesState } from './useWebSources';

export function useWebsiteRefresh(agentId: string, sources: WebSourcesState) {
    const { t } = useI18n();

    async function set(source: WebSource, interval: WebsiteRefreshInterval): Promise<void> {
        const previous = source.refresh_interval;
        if (previous === interval) return;

        sources.updateSource(source.id, { refresh_interval: interval });

        try {
            await setWebsiteRefresh(agentId, source.id, interval);
            toast.success(t('agents.sources.web.refresh_saved'));
        } catch (error) {
            sources.updateSource(source.id, { refresh_interval: previous });
            toast.error(errorMessage(error, t('agents.sources.web.refresh_failed')));
        }
    }

    return { set };
}
