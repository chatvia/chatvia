/**
 * The inline "discovered links" panel under a website: loading a page of
 * links (50 per page) on expand, server-side search with a 500 ms debounce,
 * paging, ticking links and deleting one or several. State is kept per
 * website id; the links themselves live on the website (`discovered_links`).
 */
import { errorMessage } from '@/api/http';
import { deleteWebsiteLink, deleteWebsiteLinks, listWebsiteLinks, type WebsiteLinkRecord } from '@/api/webSources';
import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { WebLinkQuota } from './useWebLinkQuota';
import type { WebsiteLink, WebSourcesState } from './useWebSources';

export interface LinkPagination {
    currentPage: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
    totalLinks: number;
    isLoading: boolean;
}

const SEARCH_DEBOUNCE_MS = 500;

/** A link record as the page shows it: untrained links count as trained, a missing crawl date falls back to the discovery date. */
export function toWebsiteLink(record: WebsiteLinkRecord): WebsiteLink {
    return {
        id: record.id,
        url: record.url,
        crawled_at: record.crawled_at || record.created_at || '',
        status: record.status || 'ingested',
    };
}

export function useDiscoveredLinks(agentId: string, sources: WebSourcesState, quota: WebLinkQuota) {
    const { t } = useI18n();

    const expandedSources = ref<Map<number, boolean>>(new Map());
    const selectedLinks = ref<Map<number, Set<number>>>(new Map());
    const searchQueries = ref<Map<number, string>>(new Map());
    const pagination = ref<Map<number, LinkPagination>>(new Map());
    const searchTimers = new Map<number, number>();

    const searchQueryFor = (websiteId: number): string => searchQueries.value.get(websiteId) || '';
    const paginationFor = (websiteId: number): LinkPagination | undefined => pagination.value.get(websiteId);
    const selectedFor = (websiteId: number): Set<number> => selectedLinks.value.get(websiteId) || new Set();
    const isExpanded = (websiteId: number): boolean => expandedSources.value.get(websiteId) || false;

    const ensureSearchQuery = (websiteId: number): void => {
        if (!searchQueries.value.has(websiteId)) {
            searchQueries.value.set(websiteId, '');
        }
    };

    /** Loads one page of a website's links. Never throws: a failure yields an empty page. */
    async function fetchLinks(websiteId: number, page = 1, search = ''): Promise<WebsiteLink[]> {
        const current = pagination.value.get(websiteId) || {
            currentPage: 1,
            hasNextPage: false,
            hasPrevPage: false,
            totalLinks: 0,
            isLoading: false,
        };
        pagination.value.set(websiteId, { ...current, isLoading: true });

        try {
            const result = await listWebsiteLinks(agentId, websiteId, page, search);

            // A non-JSON answer (e.g. an expired session) has no data array.
            if (!result || !Array.isArray(result.data)) {
                pagination.value.set(websiteId, { ...current, isLoading: false });
                return [];
            }

            pagination.value.set(websiteId, {
                currentPage: result.current_page || page,
                hasNextPage: !!result.next_page_url,
                hasPrevPage: !!result.prev_page_url,
                totalLinks: result.to || 0,
                isLoading: false,
            });

            return result.data.map(toWebsiteLink);
        } catch {
            const failed = pagination.value.get(websiteId);
            if (failed) {
                pagination.value.set(websiteId, { ...failed, isLoading: false });
            }

            return [];
        }
    }

    async function showPage(websiteId: number, page: number): Promise<void> {
        const links = await fetchLinks(websiteId, page, searchQueryFor(websiteId));
        sources.updateSource(websiteId, { discovered_links: links });
    }

    /** After discovery finishes: load the first page, then refresh the list for the real link count. */
    async function loadAfterCrawl(websiteId: number): Promise<void> {
        const links = await fetchLinks(websiteId);
        if (sources.findSource(websiteId)) {
            sources.updateSource(websiteId, { discovered_links: links });
            ensureSearchQuery(websiteId);
        }

        await sources.fetchWebSources(true);
    }

    /** Lazy-loads the links the first time a crawled or trained website is expanded. */
    async function loadOnExpand(websiteId: number, isExpanding: boolean): Promise<void> {
        if (!isExpanding) return;

        const source = sources.findSource(websiteId);
        if (!source || source.discovered_links) return;
        if (source.status !== 'crawled' && source.status !== 'ingested') return;

        const links = await fetchLinks(websiteId);
        if (sources.findSource(websiteId)) {
            sources.updateSource(websiteId, { discovered_links: links });
            ensureSearchQuery(websiteId);
        }
    }

    /** Expands or collapses the links of a trained website. */
    function toggleExpanded(websiteId: number): void {
        const wasExpanded = isExpanded(websiteId);
        const next = new Map(expandedSources.value);
        next.set(websiteId, !wasExpanded);

        if (!wasExpanded) {
            void loadOnExpand(websiteId, true);
            if (!selectedLinks.value.has(websiteId)) {
                selectedLinks.value.set(websiteId, new Set());
            }
            ensureSearchQuery(websiteId);
        }

        expandedSources.value = next;
    }

    function search(websiteId: number, query: string): void {
        searchQueries.value.set(websiteId, query);

        const pending = searchTimers.get(websiteId);
        if (pending) {
            clearTimeout(pending);
        }

        const timer = window.setTimeout(async () => {
            await showPage(websiteId, 1);
            searchTimers.delete(websiteId);
        }, SEARCH_DEBOUNCE_MS);

        searchTimers.set(websiteId, timer);
    }

    async function nextPage(websiteId: number): Promise<void> {
        const current = paginationFor(websiteId);
        if (!current || !current.hasNextPage || current.isLoading) return;

        await showPage(websiteId, current.currentPage + 1);
    }

    async function prevPage(websiteId: number): Promise<void> {
        const current = paginationFor(websiteId);
        if (!current || !current.hasPrevPage || current.isLoading) return;

        await showPage(websiteId, current.currentPage - 1);
    }

    function toggleLink(websiteId: number, linkId: number, checked: boolean | 'indeterminate'): void {
        const selected = selectedFor(websiteId);
        if (checked === true) {
            selected.add(linkId);
        } else {
            selected.delete(linkId);
        }
        selectedLinks.value.set(websiteId, selected);
    }

    /** Ticks or unticks every link on the current page, keeping picks from other pages. */
    function toggleAllOnPage(websiteId: number, links: WebsiteLink[], checked: boolean | 'indeterminate'): void {
        const selected = selectedFor(websiteId);
        for (const link of links) {
            if (checked === true) {
                selected.add(link.id);
            } else {
                selected.delete(link.id);
            }
        }
        selectedLinks.value.set(websiteId, selected);
    }

    function pageSelectionState(websiteId: number, links: WebsiteLink[]): { allSelected: boolean; someSelected: boolean } {
        if (links.length === 0) return { allSelected: false, someSelected: false };

        const selected = selectedFor(websiteId);
        const selectedOnPage = links.filter((link) => selected.has(link.id)).length;

        return {
            allSelected: selectedOnPage === links.length,
            someSelected: selectedOnPage > 0 && selectedOnPage < links.length,
        };
    }

    /** Reloads the current page after a delete and lowers the website's link count. */
    async function reloadAfterDelete(websiteId: number, deletedCount: number): Promise<void> {
        const links = await fetchLinks(websiteId, paginationFor(websiteId)?.currentPage || 1, searchQueryFor(websiteId));
        const source = sources.findSource(websiteId);
        if (source) {
            const urlsCount = source.urls_count && source.urls_count >= deletedCount ? source.urls_count - deletedCount : source.urls_count;
            sources.updateSource(websiteId, { discovered_links: links, urls_count: urlsCount });
        }

        await quota.refreshUsage();
    }

    async function deleteLink(websiteId: number, linkId: number): Promise<void> {
        // A website has to keep at least one link.
        if ((sources.findSource(websiteId)?.urls_count || 0) <= 1) {
            toast.error(t('agents.sources.web.cannot_delete_last_link'));
            return;
        }

        try {
            await deleteWebsiteLink(agentId, websiteId, linkId);
            await reloadAfterDelete(websiteId, 1);
            toast.success(t('agents.sources.web.link_deleted'));
        } catch (error) {
            toast.error(errorMessage(error, t('agents.sources.web.link_delete_failed')));
        }
    }

    async function deleteSelected(websiteId: number): Promise<void> {
        const selected = selectedLinks.value.get(websiteId);
        if (!selected || selected.size === 0) {
            toast.error(t('agents.sources.web.no_links_selected'));
            return;
        }

        const totalLinks = sources.findSource(websiteId)?.urls_count || 0;
        if (totalLinks > 0 && selected.size >= totalLinks) {
            toast.error(t('agents.sources.web.cannot_delete_all_links'));
            return;
        }

        try {
            await deleteWebsiteLinks(agentId, websiteId, Array.from(selected));
            const deletedCount = selected.size;
            selectedLinks.value.delete(websiteId);
            await reloadAfterDelete(websiteId, deletedCount);
            toast.success(t('agents.sources.web.links_deleted', deletedCount));
        } catch (error) {
            toast.error(errorMessage(error, t('agents.sources.web.links_delete_failed')));
        }
    }

    /** Drops the panel state of a website that finished training. */
    function forget(websiteId: number): void {
        selectedLinks.value.delete(websiteId);
        searchQueries.value.delete(websiteId);
        expandedSources.value.delete(websiteId);
        pagination.value.delete(websiteId);
    }

    onUnmounted(() => {
        searchTimers.forEach((timer) => clearTimeout(timer));
        searchTimers.clear();
    });

    return {
        searchQueryFor,
        paginationFor,
        selectedFor,
        isExpanded,
        loadAfterCrawl,
        loadOnExpand,
        toggleExpanded,
        search,
        nextPage,
        prevPage,
        toggleLink,
        toggleAllOnPage,
        pageSelectionState,
        deleteLink,
        deleteSelected,
        forget,
    };
}

export type DiscoveredLinks = ReturnType<typeof useDiscoveredLinks>;
