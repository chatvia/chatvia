/**
 * Emergent topic suggestions on the Topics page: load them, promote one into a
 * real topic (then reload the suggestions and the charts), or dismiss one after
 * a confirmation. The label to dismiss is kept apart from the dialog's open
 * state because reka's AlertDialogAction closes the dialog before its click
 * handler runs.
 */
import { errorMessage } from '@/api/http';
import { dismissTopicSuggestion, fetchTopicSuggestions, promoteTopicSuggestion, type TopicSuggestion } from '@/api/topics';
import { ref, toValue, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useTopicSuggestions(agentId: MaybeRefOrGetter<string>, reloadTrends: () => Promise<void>) {
    const { t } = useI18n();

    const suggestions = ref<TopicSuggestion[]>([]);
    const promotingLabel = ref<string | null>(null);
    const dismissTarget = ref<string | null>(null);
    const dismissDialogOpen = ref(false);
    const dismissing = ref(false);

    const fetchSuggestions = async () => {
        try {
            suggestions.value = await fetchTopicSuggestions(toValue(agentId));
        } catch (err) {
            console.error('Error fetching topic suggestions:', err);
        }
    };

    const promoteSuggestion = async (label: string) => {
        promotingLabel.value = label;
        try {
            await promoteTopicSuggestion(toValue(agentId), label);
            await Promise.all([fetchSuggestions(), reloadTrends()]);
            toast.success(t('agents.topics_index.promoted', { label }));
        } catch (err) {
            toast.error(errorMessage(err, t('agents.topics_index.promote_error')));
        } finally {
            promotingLabel.value = null;
        }
    };

    const askDismiss = (label: string) => {
        dismissTarget.value = label;
        dismissDialogOpen.value = true;
    };

    const confirmDismiss = async () => {
        const label = dismissTarget.value;
        dismissDialogOpen.value = false;
        if (!label) return;

        dismissing.value = true;
        try {
            await dismissTopicSuggestion(toValue(agentId), label);
            suggestions.value = suggestions.value.filter((s) => s.label !== label);
            toast.success(t('agents.topics_index.dismissed'));
        } catch (err) {
            toast.error(errorMessage(err, t('agents.topics_index.dismiss_error')));
        } finally {
            dismissing.value = false;
            dismissTarget.value = null;
        }
    };

    return {
        suggestions,
        promotingLabel,
        dismissTarget,
        dismissDialogOpen,
        dismissing,
        fetchSuggestions,
        promoteSuggestion,
        askDismiss,
        confirmDismiss,
    };
}
