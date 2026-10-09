/**
 * The agent's website sources and their split into the four stages the Web
 * page shows: being crawled, ready to train, training and trained. A refresh
 * keeps the links already loaded for each website.
 */
import { listWebsites, type WebsiteCrawlOptions, type WebsiteLinkStatus, type WebsiteRecord, type WebsiteStatus } from '@/api/webSources';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

/** A discovered page of a website, as the page lists it. */
export interface WebsiteLink {
    id: number;
    url: string;
    status: WebsiteLinkStatus;
    crawled_at: string;
}

/** A website as the page works with it. */
export interface WebSource {
    id: number;
    url: string;
    /** The website's hostname, shown as its title. */
    name: string;
    status: WebsiteStatus;
    created_at: string;
    urls_count: number;
    /** How many agents use the website; above one it is shared. */
    agents_count: number;
    options: WebsiteCrawlOptions;
    error_message?: string;
    /** The current page of discovered links; `undefined` until loaded. */
    discovered_links?: WebsiteLink[];
}

/** The hostname of a URL, or the URL itself when it does not parse. */
export function hostnameOf(url: string): string {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}

export function useWebSources(agentId: string) {
    const { t } = useI18n();

    const webSources = ref<WebSource[]>([]);
    const isLoadingSources = ref(false);
    const isInitialLoad = ref(true);

    const crawlingSources = computed(() => webSources.value.filter((source) => source.status === 'crawling' || source.status === 'pending'));
    const crawledSources = computed(() => webSources.value.filter((source) => source.status === 'crawled' || source.status === 'failed'));
    const trainingSources = computed(() => webSources.value.filter((source) => source.status === 'ingesting'));
    const completedSources = computed(() => webSources.value.filter((source) => source.status === 'ingested'));

    const toWebSource = (website: WebsiteRecord): WebSource => ({
        id: website.id,
        url: website.url,
        name: hostnameOf(website.url),
        status: website.status,
        created_at: website.created_at,
        urls_count: website.urls_count || 0,
        agents_count: website.agents_count || 0,
        options: website.options || {},
        discovered_links: findSource(website.id)?.discovered_links,
    });

    /** Reloads the list. A background refresh skips the loading state. */
    async function fetchWebSources(isBackgroundRefresh = false): Promise<void> {
        if (!isBackgroundRefresh) {
            isLoadingSources.value = true;
        }

        try {
            const websites = await listWebsites(agentId);
            webSources.value = websites.map(toWebSource);
        } catch {
            toast.error(t('agents.sources.web.load_failed'));
        } finally {
            if (!isBackgroundRefresh) {
                isLoadingSources.value = false;
            }
            isInitialLoad.value = false;
        }
    }

    function findSource(websiteId: number): WebSource | undefined {
        return webSources.value.find((source) => source.id === websiteId);
    }

    function updateSource(websiteId: number, changes: Partial<WebSource>): void {
        const index = webSources.value.findIndex((source) => source.id === websiteId);
        if (index !== -1) {
            webSources.value[index] = { ...webSources.value[index], ...changes };
        }
    }

    function removeSource(websiteId: number): void {
        webSources.value = webSources.value.filter((source) => source.id !== websiteId);
    }

    /**
     * Updates a loaded link in place. Without a website id the first website
     * whose loaded links contain the link is updated.
     */
    function updateLink(linkId: number, changes: Partial<WebsiteLink>, websiteId?: number): void {
        const source = webSources.value.find(
            (candidate) => (websiteId === undefined || candidate.id === websiteId) && candidate.discovered_links?.some((link) => link.id === linkId),
        );
        if (!source?.discovered_links) {
            return;
        }

        updateSource(source.id, {
            discovered_links: source.discovered_links.map((link) => (link.id === linkId ? { ...link, ...changes } : link)),
        });
    }

    return {
        webSources,
        isLoadingSources,
        isInitialLoad,
        crawlingSources,
        crawledSources,
        trainingSources,
        completedSources,
        fetchWebSources,
        findSource,
        updateSource,
        removeSource,
        updateLink,
    };
}

export type WebSourcesState = ReturnType<typeof useWebSources>;
