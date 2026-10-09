/**
 * Picking crawled websites for training and starting it. A website can only
 * be picked while the links of everything picked still fit in the plan's
 * remaining link quota; "select all" picks as many as fit, in list order.
 */
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { WebLinkQuota } from './useWebLinkQuota';
import type { WebSourcesState } from './useWebSources';

export function useWebsiteTraining(agentId: string, sources: WebSourcesState, quota: WebLinkQuota) {
    const { t } = useI18n();
    const { linkUsage, refreshUsage } = quota;

    const selectedWebsites = ref<Set<number>>(new Set());
    const isTraining = ref(false);

    const linksOf = (websiteId: number): number => sources.findSource(websiteId)?.urls_count || 0;

    const selectedCount = computed(() => selectedWebsites.value.size);

    const selectedTotalLinks = computed(() => {
        let total = 0;
        for (const websiteId of selectedWebsites.value) {
            total += linksOf(websiteId);
        }
        return total;
    });

    const canTrain = computed(() => {
        if (selectedCount.value === 0 || !linkUsage.value) return false;
        return selectedTotalLinks.value <= linkUsage.value.remaining;
    });

    const allSelected = computed(
        () => sources.crawledSources.value.length > 0 && sources.crawledSources.value.every((source) => selectedWebsites.value.has(source.id)),
    );
    const someSelected = computed(() => sources.crawledSources.value.some((source) => selectedWebsites.value.has(source.id)) && !allSelected.value);

    const isSelected = (websiteId: number): boolean => selectedWebsites.value.has(websiteId);

    /** Whether picking the website keeps the selection within the quota (always true before usage loads). */
    function canSelect(websiteId: number): boolean {
        if (!linkUsage.value) return true;
        if (selectedWebsites.value.has(websiteId)) return true;

        return selectedTotalLinks.value + linksOf(websiteId) <= linkUsage.value.remaining;
    }

    function deselect(websiteId: number): void {
        const next = new Set(selectedWebsites.value);
        next.delete(websiteId);
        selectedWebsites.value = next;
    }

    function toggle(websiteId: number, checked: boolean | 'indeterminate'): void {
        if (checked !== true) {
            deselect(websiteId);
            return;
        }

        if (canSelect(websiteId)) {
            selectedWebsites.value = new Set(selectedWebsites.value).add(websiteId);
            return;
        }

        const remaining = linkUsage.value?.remaining || 0;
        if (remaining <= 0) {
            toast.error(t('agents.sources.web.no_links_remaining'), {
                description: t('agents.sources.web.no_links_remaining_description'),
                duration: 5000,
            });
            return;
        }

        const websiteLinks = linksOf(websiteId);
        const exceedBy = selectedTotalLinks.value + websiteLinks - remaining;
        toast.error(t('agents.sources.web.cannot_select'), {
            description: t('agents.sources.web.cannot_select_description', { links: websiteLinks, exceedBy }),
            duration: 5000,
        });
    }

    function toggleAll(checked: boolean | 'indeterminate'): void {
        const next = new Set<number>();

        if (checked === true && linkUsage.value) {
            const remaining = linkUsage.value.remaining;
            const crawled = sources.crawledSources.value;
            let total = 0;

            for (const source of crawled) {
                const websiteLinks = source.urls_count || 0;
                if (total + websiteLinks <= remaining) {
                    next.add(source.id);
                    total += websiteLinks;
                }
            }

            if (next.size < crawled.length) {
                toast.info(t('agents.sources.web.selected_count', { selected: next.size, total: crawled.length }), {
                    description: t('agents.sources.web.selected_skipped', { skipped: crawled.length - next.size, remaining }),
                });
            }
        }

        selectedWebsites.value = next;
    }

    /** Starts training every link of one website; resolves to whether it started. */
    const trainWebsite = (websiteId: number): Promise<boolean> =>
        new Promise((resolve) => {
            router.post(
                route('agents.sources.websites.train', { agent: agentId, website: websiteId }),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => resolve(true),
                    onError: () => resolve(false),
                },
            );
        });

    /** Trains the picked websites one after another and reports the ones that failed. */
    async function trainSelected(): Promise<void> {
        if (!canTrain.value || isTraining.value) return;

        isTraining.value = true;
        let successCount = 0;
        const failedWebsites: string[] = [];

        for (const websiteId of Array.from(selectedWebsites.value)) {
            if (await trainWebsite(websiteId)) {
                successCount++;
            } else {
                failedWebsites.push(sources.findSource(websiteId)?.name || '');
            }
        }

        if (successCount > 0) {
            selectedWebsites.value = new Set();
            await refreshUsage();
        }

        if (failedWebsites.length > 0) {
            toast.error(t('agents.sources.web.training_start_failed', { websites: failedWebsites.join(', ') }));
        }

        if (successCount === 0 && failedWebsites.length === 0) {
            toast.error(t('agents.sources.web.training_start_failed_all'));
        }

        isTraining.value = false;
    }

    return {
        selectedCount,
        selectedTotalLinks,
        canTrain,
        isTraining,
        allSelected,
        someSelected,
        isSelected,
        canSelect,
        toggle,
        toggleAll,
        deselect,
        trainSelected,
    };
}

export type WebsiteTraining = ReturnType<typeof useWebsiteTraining>;
