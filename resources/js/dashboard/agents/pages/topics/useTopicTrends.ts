/**
 * The topic counts behind the Topics page charts. Every (re)load shows the
 * page's loading state; a failed load shows the server's message with a retry.
 */
import { fetchTopicTrends, type TopicTrends } from '@/dashboard/agents/api/topics';
import { errorMessage } from '@/shared/api/http';
import { ref, toValue, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';

export function useTopicTrends(agentId: MaybeRefOrGetter<string>) {
    const { t } = useI18n();

    const loading = ref(true);
    const topicsData = ref<TopicTrends | null>(null);
    const error = ref<string | null>(null);

    const fetchTopicsData = async () => {
        loading.value = true;
        error.value = null;

        try {
            topicsData.value = await fetchTopicTrends(toValue(agentId));
        } catch (err) {
            error.value = errorMessage(err, t('agents.topics_index.load_error'));
            console.error('Error fetching topics data:', err);
        } finally {
            loading.value = false;
        }
    };

    return { loading, topicsData, error, fetchTopicsData };
}
