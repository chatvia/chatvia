/**
 * The agent's text list on the Text sources page: loads it one page at a time
 * ("Load more"), reads one text in full for the edit form, and keeps each
 * row's status and training progress up to date from the two text ingestion
 * broadcasts.
 */
import { fetchTextSource, fetchTextSources, type TextSource, type TextSourceStatus } from '@/dashboard/agents/api/textSources';
import { usePagedSourceList } from '@/dashboard/agents/composables/sources/usePagedSourceList';
import { useEcho } from '@laravel/echo-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

/**
 * `.TextIngestionStarted` on `agents.{id}.ingestion.text`. Despite the name it
 * fires on every status change of a text (started, finished, failed).
 */
interface TextStatusChangedEvent {
    id: number;
    status: TextSourceStatus;
}

/** `.TextIngestionProgressed` on `agents.{id}.texts.progress`, after each chunk job. */
interface TextProgressEvent {
    text_id: number;
    /** 0-100 */
    progress: number;
}

export function useTextSources(agentId: string) {
    const { t } = useI18n();

    const list = usePagedSourceList<TextSource>((page) => fetchTextSources(agentId, page));
    const entries = list.items;
    const isLoading = ref(false);

    /** Loads the first page again. A failure keeps the list as it is. */
    async function fetchEntries(): Promise<void> {
        isLoading.value = true;
        try {
            await list.reload();
        } catch (error) {
            console.error('Failed to fetch text entries:', error);
        } finally {
            isLoading.value = false;
        }
    }

    /** Adds the next page. A failure keeps the list as it is. */
    async function loadMore(): Promise<void> {
        await list.loadMore().catch((error: unknown) => console.error('Failed to fetch text entries:', error));
    }

    /** The full text of an entry, or null when it could not be loaded (the user gets a toast). */
    async function fetchContent(entry: TextSource): Promise<string | null> {
        isLoading.value = true;
        try {
            const text = await fetchTextSource(agentId, entry.id);

            return text.content;
        } catch {
            toast.error(t('agents.sources.text.load_failed'), {
                description: t('agents.sources.text.load_failed_description'),
            });

            return null;
        } finally {
            isLoading.value = false;
        }
    }

    async function onStatusChanged({ id, status }: TextStatusChangedEvent): Promise<void> {
        const index = entries.value.findIndex((entry) => entry.id === id);

        if (index === -1) {
            // A text this page has not loaded yet: reload the list.
            await fetchEntries();
            return;
        }

        entries.value[index] = { ...entries.value[index], status };

        switch (status) {
            case 'ingesting':
                toast.info(t('agents.sources.text.processing_started'), {
                    description: t('agents.sources.text.processing_started_description'),
                });
                break;
            case 'ingested':
                toast.success(t('agents.sources.text.processing_success'), {
                    description: t('agents.sources.text.processing_success_description'),
                });
                break;
            case 'failed':
                toast.error(t('agents.sources.text.processing_failed'), {
                    description: t('agents.sources.text.processing_failed_description'),
                });
                break;
        }
    }

    function onProgress({ text_id, progress }: TextProgressEvent): void {
        if (!text_id || progress === undefined) {
            return;
        }

        const index = entries.value.findIndex((entry) => entry.id === text_id);

        if (index === -1) {
            return;
        }

        // Replace the array so every watcher of the list sees the change.
        const updated = [...entries.value];
        updated[index] = {
            ...updated[index],
            progress,
            status: progress >= 100 ? 'ingested' : 'ingesting',
        };
        entries.value = updated;

        if (progress >= 100) {
            // Reload a moment later, once the server has stored the final state.
            setTimeout(() => {
                fetchEntries();
            }, 1000);
        }
    }

    useEcho<TextStatusChangedEvent>(`agents.${agentId}.ingestion.text`, '.TextIngestionStarted', (event) => {
        onStatusChanged(event);
    });

    useEcho<TextProgressEvent>(`agents.${agentId}.texts.progress`, '.TextIngestionProgressed', (event) => {
        onProgress(event);
    });

    return {
        entries,
        total: list.total,
        hasMore: list.hasMore,
        isLoadingMore: list.isLoadingMore,
        isLoading,
        fetchEntries,
        loadMore,
        fetchContent,
    };
}
