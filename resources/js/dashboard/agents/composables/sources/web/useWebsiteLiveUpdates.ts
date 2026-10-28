/**
 * Live updates for the Web sources page over Reverb: a website's crawl and
 * training status (`agents.{id}.crawler` / `.WebsiteCrawlStarted`) and the
 * training status of single links (`agents.{id}.web.ingestion.url` /
 * `.UrlIngestionStarted`). Each broadcast is applied to the page state.
 */
import type { WebsiteLinkStatus, WebsiteStatus } from '@/dashboard/agents/api/webSources';
import { useEcho } from '@laravel/echo-vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { DiscoveredLinks } from './useDiscoveredLinks';
import type { WebsiteLinksDialog } from './useWebsiteLinksDialog';
import type { WebsiteTraining } from './useWebsiteTraining';
import type { WebSourcesState } from './useWebSources';

/** Payload of `WebsiteCrawlStarted`. `started` is an older name for `crawling`. */
interface WebsiteStatusBroadcast {
    id?: number;
    status: WebsiteStatus | 'started';
    error_message?: string;
}

/** Payload of `UrlIngestionStarted`. It carries the link id, not its website. */
interface LinkStatusBroadcast {
    id?: number;
    url: string;
    status: WebsiteLinkStatus;
    crawled_at: string;
}

interface WebPageState {
    sources: WebSourcesState;
    links: DiscoveredLinks;
    training: WebsiteTraining;
    linksDialog: WebsiteLinksDialog;
}

export function useWebsiteLiveUpdates(agentId: string, { sources, links, training, linksDialog }: WebPageState) {
    const { t } = useI18n();

    /** The website a broadcast is about; falls back to the first one still in progress when the id is missing. */
    function findTarget(websiteId: number | undefined) {
        if (websiteId) {
            return sources.findSource(websiteId);
        }

        return sources.webSources.value.find((source) => ['crawling', 'pending', 'crawled', 'ingesting'].includes(source.status));
    }

    async function onWebsiteStatus(event: WebsiteStatusBroadcast): Promise<void> {
        const websiteId = event.id;
        let source = findTarget(websiteId);

        // Discovery is near-instant, so "crawled" can arrive before the
        // just-added website is in the local list. Reload once before giving up.
        if (!source && websiteId) {
            await sources.fetchWebSources(true);
            source = sources.findSource(websiteId);
        }

        if (!source) return;

        switch (event.status) {
            case 'crawling':
            case 'started':
                sources.updateSource(source.id, { status: 'crawling' });
                toast.success(t('agents.sources.web.crawling_started', { name: source.name }));
                break;

            case 'crawled':
                // The real link count arrives with the links.
                sources.updateSource(source.id, { status: 'crawled', urls_count: 0 });
                toast.success(t('agents.sources.web.crawling_completed', { name: source.name }));
                if (websiteId) {
                    void links.loadAfterCrawl(websiteId);
                }
                break;

            case 'ingesting':
                sources.updateSource(source.id, { status: 'ingesting' });
                toast.info(t('agents.sources.web.training_started', { name: source.name }));
                break;

            case 'ingested':
                // Trained websites show their links in the dialog, not inline.
                sources.updateSource(source.id, { status: 'ingested', discovered_links: undefined });
                toast.success(t('agents.sources.web.training_completed', { name: source.name }));
                if (websiteId) {
                    training.deselect(websiteId);
                    links.forget(websiteId);
                }
                break;

            case 'failed':
                sources.updateSource(source.id, {
                    status: 'failed',
                    error_message: event.error_message || t('agents.sources.web.operation_failed_generic'),
                });
                toast.error(t('agents.sources.web.operation_failed', { name: source.name }));
                break;
        }
    }

    function onLinkStatus(event: LinkStatusBroadcast): void {
        const linkId = event.id;
        if (!linkId) return;

        linksDialog.applyBroadcast(linkId, event.status, event.crawled_at);
        sources.updateLink(linkId, { status: event.status, crawled_at: event.crawled_at });

        if (event.status === 'ingested') {
            linksDialog.markTrained(linkId);
        } else if (event.status === 'failed') {
            toast.error(t('agents.sources.web.link_training_failed'));
        }
    }

    useEcho<WebsiteStatusBroadcast>(`agents.${agentId}.crawler`, '.WebsiteCrawlStarted', (event) => {
        void onWebsiteStatus(event);
    });

    useEcho<LinkStatusBroadcast>(`agents.${agentId}.web.ingestion.url`, '.UrlIngestionStarted', onLinkStatus);
}
