/**
 * A source list the server sends one page at a time, shown with a "Load more"
 * button: the rows loaded so far, the total, and whether more pages are left.
 * `reload` starts again from the first page; `loadMore` adds the next one. An
 * answer that arrives after a newer request started is dropped.
 */
import type { LengthAwarePage } from '@/api/pagination';
import { computed, ref, type Ref } from 'vue';

export function usePagedSourceList<T extends { id: number }>(fetchPage: (page: number) => Promise<LengthAwarePage<T>>) {
    const items = ref<T[]>([]) as Ref<T[]>;
    const total = ref(0);
    const currentPage = ref(0);
    const lastPage = ref(0);
    const isLoading = ref(false);
    const isLoadingMore = ref(false);
    let request = 0;

    const hasMore = computed(() => currentPage.value < lastPage.value);

    function apply(page: LengthAwarePage<T>, rows: T[]): void {
        items.value = rows;
        total.value = page.total;
        currentPage.value = page.current_page;
        lastPage.value = page.last_page;
    }

    /** Loads the first page again. Throws when the request fails; the rows stay as they were. */
    async function reload(): Promise<void> {
        const current = ++request;
        isLoading.value = true;

        try {
            const page = await fetchPage(1);
            if (current === request) {
                apply(page, page.data);
            }
        } finally {
            if (current === request) {
                isLoading.value = false;
            }
        }
    }

    /** Adds the next page. Throws when the request fails. */
    async function loadMore(): Promise<void> {
        if (!hasMore.value || isLoadingMore.value) return;

        const current = ++request;
        isLoadingMore.value = true;

        try {
            const page = await fetchPage(currentPage.value + 1);
            if (current === request) {
                // A row can move to the next page when new ones arrived meanwhile.
                const known = new Set(items.value.map((item) => item.id));
                apply(page, [...items.value, ...page.data.filter((item) => !known.has(item.id))]);
            }
        } finally {
            isLoadingMore.value = false;
        }
    }

    /** Drops a row the server no longer lists (it was deleted or unlinked). */
    function remove(id: number): void {
        const before = items.value.length;
        items.value = items.value.filter((item) => item.id !== id);
        total.value = Math.max(0, total.value - (before - items.value.length));
    }

    /** Empties the list (its rows were deleted on the server). */
    function clear(): void {
        items.value = [];
        total.value = 0;
        currentPage.value = 0;
        lastPage.value = 0;
    }

    return { items, total, hasMore, isLoading, isLoadingMore, reload, loadMore, remove, clear };
}

export type PagedSourceList<T extends { id: number }> = ReturnType<typeof usePagedSourceList<T>>;
