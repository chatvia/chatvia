/**
 * State of the topic manager dialog on the Topics page: every topic the agent
 * may use (searchable, 10 per page), which of them are attached, a form to
 * create a new one, and attach/remove. After each change it reloads the list
 * and then the page's charts (`reloadTrends`) before confirming with a toast.
 */
import { attachTopic, createTopic, detachTopic, fetchAgentTopics, fetchAvailableTopics, type Topic } from '@/dashboard/agents/api/topics';
import { errorMessage } from '@/shared/api/http';
import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const ITEMS_PER_PAGE = 10;

export function useTopicLibrary(agentId: MaybeRefOrGetter<string>, reloadTrends: () => Promise<void>) {
    const { t } = useI18n();

    const allTopics = ref<Topic[]>([]);
    const agentTopics = ref<Topic[]>([]);
    const loadingTopics = ref(false);
    const searchQuery = ref('');
    const newTopicName = ref('');
    const newTopicDescription = ref('');
    const showAddForm = ref(false);
    const isAddingTopic = ref(false);
    const currentPage = ref(1);

    const fetchAllTopics = async () => {
        loadingTopics.value = true;
        try {
            agentTopics.value = await fetchAgentTopics(toValue(agentId));
            allTopics.value = await fetchAvailableTopics(toValue(agentId));
        } catch (err) {
            console.error('Error fetching topics:', err);
            toast.error(t('agents.topics_index.load_topics_error'));
        } finally {
            loadingTopics.value = false;
        }
    };

    const filteredTopics = computed(() => {
        if (!searchQuery.value) return allTopics.value;

        const query = searchQuery.value.toLowerCase();
        return allTopics.value.filter((topic) => topic.topic.toLowerCase().includes(query) || topic.description.toLowerCase().includes(query));
    });

    const paginatedTopics = computed(() => {
        const start = (currentPage.value - 1) * ITEMS_PER_PAGE;
        return filteredTopics.value.slice(start, start + ITEMS_PER_PAGE);
    });

    const totalPages = computed(() => Math.ceil(filteredTopics.value.length / ITEMS_PER_PAGE));

    // A new search starts on the first page.
    watch(searchQuery, () => {
        currentPage.value = 1;
    });

    const isTopicAssociated = (topicId: number) => agentTopics.value.some((topic) => topic.id === topicId);

    const refresh = async () => {
        await fetchAllTopics();
        await reloadTrends();
    };

    const addTopicToAgent = async (topicId: number) => {
        try {
            await attachTopic(toValue(agentId), topicId);
            await refresh();
            toast.success(t('agents.topics_index.added'));
        } catch (err) {
            console.error('Error adding topic:', err);
            toast.error(errorMessage(err, t('agents.topics_index.add_error')));
        }
    };

    const removeTopicFromAgent = async (topicId: number) => {
        try {
            await detachTopic(toValue(agentId), topicId);
            await refresh();
            toast.success(t('agents.topics_index.removed'));
        } catch (err) {
            console.error('Error removing topic:', err);
            toast.error(errorMessage(err, t('agents.topics_index.remove_error')));
        }
    };

    const createNewTopic = async () => {
        if (!newTopicName.value.trim() || !newTopicDescription.value.trim()) {
            toast.error(t('agents.topics_index.fill_fields'));
            return;
        }

        isAddingTopic.value = true;
        try {
            await createTopic(toValue(agentId), {
                topic: newTopicName.value.trim(),
                description: newTopicDescription.value.trim(),
            });

            newTopicName.value = '';
            newTopicDescription.value = '';
            showAddForm.value = false;

            await refresh();
            toast.success(t('agents.topics_index.created'));
        } catch (err) {
            console.error('Error creating topic:', err);
            toast.error(errorMessage(err, t('agents.topics_index.create_error')));
        } finally {
            isAddingTopic.value = false;
        }
    };

    /** Clears the search, the new-topic form and the page, for the next time the dialog opens. */
    const reset = () => {
        searchQuery.value = '';
        showAddForm.value = false;
        newTopicName.value = '';
        newTopicDescription.value = '';
        currentPage.value = 1;
    };

    return {
        loadingTopics,
        searchQuery,
        newTopicName,
        newTopicDescription,
        showAddForm,
        isAddingTopic,
        currentPage,
        filteredTopics,
        paginatedTopics,
        totalPages,
        fetchAllTopics,
        isTopicAssociated,
        addTopicToAgent,
        removeTopicFromAgent,
        createNewTopic,
        reset,
    };
}
