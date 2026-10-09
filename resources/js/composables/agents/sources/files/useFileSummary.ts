/**
 * The "file summary" dialog: loads the AI analysis of a trained file (topics,
 * audience, style, …). Only files that finished training have one.
 */
import { fetchFileSummary, type FileSummary, type SourceFile } from '@/api/fileSources';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useFileSummary(agentId: string) {
    const { t } = useI18n();

    const isOpen = ref(false);
    const file = ref<SourceFile | null>(null);
    const summary = ref<FileSummary | null>(null);
    const isLoading = ref(false);

    async function show(target: SourceFile): Promise<void> {
        if (target.status !== 'ingested') {
            toast.error(t('agents.sources.files.summary_only_completed'));
            return;
        }

        file.value = target;
        isOpen.value = true;
        isLoading.value = true;
        summary.value = null;

        try {
            summary.value = await fetchFileSummary(agentId, target.id);
            if (summary.value === null) {
                toast.error(t('agents.sources.files.summary_none'));
            }
        } catch {
            toast.error(t('agents.sources.files.summary_load_failed'));
        } finally {
            isLoading.value = false;
        }
    }

    return { isOpen, file, summary, isLoading, show };
}
