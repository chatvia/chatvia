/**
 * Picking crawled websites for training and starting it, one website after
 * another.
 */
import { train } from '@/routes/agents/sources/websites';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { WebSourcesState } from './useWebSources';

export function useWebsiteTraining(agentId: string, sources: WebSourcesState) {
    const { t } = useI18n();

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

    const canTrain = computed(() => selectedCount.value > 0);

    const allSelected = computed(
        () => sources.crawledSources.value.length > 0 && sources.crawledSources.value.every((source) => selectedWebsites.value.has(source.id)),
    );
    const someSelected = computed(() => sources.crawledSources.value.some((source) => selectedWebsites.value.has(source.id)) && !allSelected.value);

    const isSelected = (websiteId: number): boolean => selectedWebsites.value.has(websiteId);

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

        selectedWebsites.value = new Set(selectedWebsites.value).add(websiteId);
    }

    function toggleAll(checked: boolean | 'indeterminate'): void {
        selectedWebsites.value = checked === true ? new Set(sources.crawledSources.value.map((source) => source.id)) : new Set();
    }

    /** Starts training every link of one website; resolves to whether it started. */
    const trainWebsite = (websiteId: number): Promise<boolean> =>
        new Promise((resolve) => {
            router.post(
                train.url({ agent: agentId, website: websiteId }),
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
        toggle,
        toggleAll,
        deselect,
        trainSelected,
    };
}

export type WebsiteTraining = ReturnType<typeof useWebsiteTraining>;
