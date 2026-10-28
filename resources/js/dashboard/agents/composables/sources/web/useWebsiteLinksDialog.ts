/**
 * The "all links" dialog of a training or trained website: paging and
 * searching its links, ticking and deleting them, retraining a single link,
 * and the short check-mark shown when a link finishes training.
 */
import {
    deleteWebsiteLink,
    deleteWebsiteLinks,
    listWebsiteLinks,
    retrainWebsiteLink,
    type WebsiteLinkStatus,
} from '@/dashboard/agents/api/webSources';
import { errorMessage } from '@/shared/api/http';
import { computed, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { toWebsiteLink } from './useDiscoveredLinks';
import type { WebsiteLink, WebSource, WebSourcesState } from './useWebSources';

export interface DialogLinkPagination {
    currentPage: number;
    lastPage: number;
    total: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
}

const SEARCH_DEBOUNCE_MS = 500;
const TRAINED_MARK_MS = 2000;

const firstPage = (): DialogLinkPagination => ({ currentPage: 1, lastPage: 1, total: 0, hasNextPage: false, hasPrevPage: false });

export function useWebsiteLinksDialog(agentId: string, sources: WebSourcesState) {
    const { t } = useI18n();

    const isOpen = ref(false);
    const website = ref<WebSource | null>(null);
    const links = ref<WebsiteLink[]>([]);
    const isLoading = ref(false);
    const pagination = ref<DialogLinkPagination>(firstPage());
    const searchQuery = ref('');
    const selectedIds = ref<Set<number>>(new Set());
    /** Links that just finished training, shown with a check mark for a moment. */
    const justTrained = ref<Map<number, boolean>>(new Map());

    let searchTimer: number | null = null;
    const trainedTimers = new Map<number, number>();

    const allSelected = computed(() => links.value.length > 0 && links.value.every((link) => selectedIds.value.has(link.id)));
    const someSelected = computed(() => {
        if (links.value.length === 0) return false;
        const count = links.value.filter((link) => selectedIds.value.has(link.id)).length;
        return count > 0 && count < links.value.length;
    });

    const clearSearchTimer = (): void => {
        if (searchTimer) {
            clearTimeout(searchTimer);
            searchTimer = null;
        }
    };

    async function fetchPage(websiteId: number, page = 1, search = ''): Promise<void> {
        isLoading.value = true;
        try {
            const result = await listWebsiteLinks(agentId, websiteId, page, search);

            // A non-JSON answer (e.g. an expired session) has no data array.
            if (!result || !Array.isArray(result.data)) {
                links.value = [];
                return;
            }

            links.value = result.data.map(toWebsiteLink);
            pagination.value = {
                currentPage: result.current_page || page,
                lastPage: result.last_page || 1,
                total: result.total || 0,
                hasNextPage: !!result.next_page_url,
                hasPrevPage: !!result.prev_page_url,
            };
        } catch {
            toast.error(t('agents.sources.web.dialog_links_load_failed'));
        } finally {
            isLoading.value = false;
        }
    }

    const reloadCurrentPage = (): Promise<void> =>
        website.value ? fetchPage(website.value.id, pagination.value.currentPage, searchQuery.value) : Promise.resolve();

    async function open(source: WebSource): Promise<void> {
        website.value = source;
        isOpen.value = true;
        await fetchPage(source.id, 1);
    }

    function close(): void {
        isOpen.value = false;
        website.value = null;
        links.value = [];
        searchQuery.value = '';
        selectedIds.value.clear();
        clearSearchTimer();
        pagination.value = firstPage();
    }

    async function nextPage(): Promise<void> {
        if (!website.value || !pagination.value.hasNextPage || isLoading.value) return;
        await fetchPage(website.value.id, pagination.value.currentPage + 1, searchQuery.value);
    }

    async function prevPage(): Promise<void> {
        if (!website.value || !pagination.value.hasPrevPage || isLoading.value) return;
        await fetchPage(website.value.id, pagination.value.currentPage - 1, searchQuery.value);
    }

    function search(query: string): void {
        searchQuery.value = query;
        clearSearchTimer();

        searchTimer = window.setTimeout(async () => {
            if (!website.value) return;
            await fetchPage(website.value.id, 1, searchQuery.value);
            searchTimer = null;
        }, SEARCH_DEBOUNCE_MS);
    }

    function toggleLink(linkId: number, checked: boolean | 'indeterminate'): void {
        if (checked === true) {
            selectedIds.value.add(linkId);
        } else {
            selectedIds.value.delete(linkId);
        }
    }

    function toggleAll(checked: boolean | 'indeterminate'): void {
        for (const link of links.value) {
            if (checked === true) {
                selectedIds.value.add(link.id);
            } else {
                selectedIds.value.delete(link.id);
            }
        }
    }

    async function deleteLink(linkId: number): Promise<void> {
        if (!website.value) return;

        try {
            await deleteWebsiteLink(agentId, website.value.id, linkId);
            toast.success(t('agents.sources.web.link_deleted'));
            await reloadCurrentPage();
        } catch (error) {
            toast.error(errorMessage(error, t('agents.sources.web.link_delete_failed')));
        }
    }

    async function deleteSelected(): Promise<void> {
        if (!website.value || selectedIds.value.size === 0) {
            toast.error(t('agents.sources.web.no_links_selected'));
            return;
        }

        try {
            await deleteWebsiteLinks(agentId, website.value.id, Array.from(selectedIds.value));
            const deletedCount = selectedIds.value.size;
            selectedIds.value.clear();
            toast.success(t('agents.sources.web.links_deleted', deletedCount));
            await reloadCurrentPage();
        } catch (error) {
            toast.error(errorMessage(error, t('agents.sources.web.links_delete_failed')));
        }
    }

    /** Applies a status change to a link shown in the dialog. */
    function updateLink(linkId: number, changes: Partial<WebsiteLink>): void {
        links.value = links.value.map((link) => (link.id === linkId ? { ...link, ...changes } : link));
    }

    /** Retrains one link, showing it as pending right away and reverting if the request fails. */
    async function retrainLink(linkId: number): Promise<void> {
        if (!website.value) return;
        const websiteId = website.value.id;

        try {
            updateLink(linkId, { status: 'pending' });
            sources.updateLink(linkId, { status: 'pending' }, websiteId);

            await retrainWebsiteLink(agentId, websiteId, linkId);
            toast.success(t('agents.sources.web.link_retrain_started'));
        } catch (error) {
            toast.error(errorMessage(error, t('agents.sources.web.link_retrain_failed')));
            updateLink(linkId, { status: 'ingested' });
        }
    }

    /** Called for every link status broadcast while the dialog shows links. */
    function applyBroadcast(linkId: number, status: WebsiteLinkStatus, crawledAt: string): void {
        if (website.value && links.value.length > 0 && links.value.some((link) => link.id === linkId)) {
            updateLink(linkId, { status, crawled_at: crawledAt });
        }
    }

    /** Shows the check mark next to a link for two seconds. */
    function markTrained(linkId: number): void {
        justTrained.value.set(linkId, true);

        const pending = trainedTimers.get(linkId);
        if (pending) {
            clearTimeout(pending);
        }

        trainedTimers.set(
            linkId,
            window.setTimeout(() => {
                justTrained.value.delete(linkId);
                trainedTimers.delete(linkId);
            }, TRAINED_MARK_MS),
        );
    }

    onUnmounted(() => {
        clearSearchTimer();
        trainedTimers.forEach((timer) => clearTimeout(timer));
        trainedTimers.clear();
        justTrained.value.clear();
    });

    return {
        isOpen,
        website,
        links,
        isLoading,
        pagination,
        searchQuery,
        selectedIds,
        justTrained,
        allSelected,
        someSelected,
        open,
        close,
        nextPage,
        prevPage,
        search,
        toggleLink,
        toggleAll,
        deleteLink,
        deleteSelected,
        retrainLink,
        applyBroadcast,
        markTrained,
    };
}

export type WebsiteLinksDialog = ReturnType<typeof useWebsiteLinksDialog>;
