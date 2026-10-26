<script lang="ts" setup>
/**
 * Manage Topics: list, search, add, rename and remove the topics the agent's
 * conversations are sorted into. Changing topics needs the
 * `agent.topics.manage` team permission; without it the buttons only explain
 * why nothing happens.
 */
import { errorMessage } from '@/api/http';
import { createTopic, detachTopic, fetchAgentTopics, type Topic, type TopicInput, updateTopic } from '@/api/topics';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { topics as topicsAnalytics } from '@/routes/agents/analytics';
import { LoaderCircle, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import TopicFormDialog from './components/TopicFormDialog.vue';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

// Permission checks
const { hasPermission } = useTeamPermissions();
const canManageTopics = computed(() => hasPermission('agent.topics.manage'));

const loading = ref(true);
const topics = ref<Topic[]>([]);
const searchQuery = ref('');
const isAddDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const isSubmitting = ref(false);

// The topic being edited or deleted; kept apart from the dialogs' open state.
const currentTopic = ref<Topic | null>(null);
const form = ref<TopicInput>({
    topic: '',
    description: '',
});

const filteredTopics = computed(() => {
    if (!searchQuery.value) return topics.value;
    return topics.value.filter((topic) => topic.topic.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const fetchTopics = async () => {
    loading.value = true;
    try {
        topics.value = await fetchAgentTopics(props.agent.external_id);
    } catch (error) {
        toast.error(errorMessage(error, t('agents.topics.manage.load_error')));
    } finally {
        loading.value = false;
    }
};

/** Shows the "no permission" toast and returns false when the viewer may not change topics. */
const ensureCanManage = (): boolean => {
    if (canManageTopics.value) return true;

    toast.error(t('agents.topics.manage.permission_denied'), {
        description: t('agents.topics.manage.permission_denied_description'),
    });
    return false;
};

const openAddDialog = () => {
    if (!ensureCanManage()) return;

    form.value = {
        topic: '',
        description: '',
    };
    isAddDialogOpen.value = true;
};

const openEditDialog = (topic: Topic) => {
    if (!ensureCanManage()) return;

    currentTopic.value = topic;
    form.value = {
        topic: topic.topic,
        description: topic.description,
    };
    isEditDialogOpen.value = true;
};

const openDeleteDialog = (topic: Topic) => {
    if (!ensureCanManage()) return;

    currentTopic.value = topic;
    isDeleteDialogOpen.value = true;
};

const handleAdd = async () => {
    if (!form.value.topic || !form.value.description) {
        toast.error(t('agents.topics.manage.fill_all_fields'));
        return;
    }

    isSubmitting.value = true;
    try {
        await createTopic(props.agent.external_id, form.value);
        toast.success(t('agents.topics.manage.created'));
        isAddDialogOpen.value = false;
        fetchTopics();
    } catch (error) {
        toast.error(errorMessage(error, t('agents.topics.manage.create_error')));
    } finally {
        isSubmitting.value = false;
    }
};

const handleEdit = async () => {
    if (!form.value.topic || !form.value.description) {
        toast.error(t('agents.topics.manage.fill_all_fields'));
        return;
    }
    if (!currentTopic.value) return;

    isSubmitting.value = true;
    try {
        await updateTopic(props.agent.external_id, currentTopic.value.id, form.value);
        toast.success(t('agents.topics.manage.updated'));
        isEditDialogOpen.value = false;
        fetchTopics();
    } catch (error) {
        toast.error(errorMessage(error, t('agents.topics.manage.update_error')));
    } finally {
        isSubmitting.value = false;
    }
};

const handleDelete = async () => {
    if (!currentTopic.value) return;

    isSubmitting.value = true;
    try {
        await detachTopic(props.agent.external_id, currentTopic.value.id);
        toast.success(t('agents.topics.manage.deleted'));
        isDeleteDialogOpen.value = false;
        fetchTopics();
    } catch (error) {
        toast.error(errorMessage(error, t('agents.topics.manage.delete_error')));
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchTopics();
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.topics.manage.title')" :description="t('agents.topics.manage.description')">
        <div class="space-y-6">
            <!-- Search and Add -->
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div class="relative w-full sm:w-96">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
                    <Input v-model="searchQuery" :placeholder="t('agents.topics.manage.search_placeholder')" class="ps-9" />
                </div>
                <Button @click="openAddDialog">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('agents.topics.manage.add_topic') }}
                </Button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
            </div>

            <!-- Topics List -->
            <Card v-else>
                <CardContent class="!p-0">
                    <div v-if="filteredTopics.length === 0" class="text-muted-foreground p-8 text-center">
                        <p v-if="searchQuery">{{ t('agents.topics.manage.no_results', { query: searchQuery }) }}</p>
                        <p v-else>{{ t('agents.topics.manage.empty') }}</p>
                    </div>
                    <div v-else class="divide-y">
                        <div
                            v-for="topic in filteredTopics"
                            :key="topic.id"
                            class="hover:bg-muted/50 flex items-center justify-between p-4 transition-colors"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ topic.topic }}</p>
                            </div>
                            <div class="ms-4 flex items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="t('agents.topics.manage.edit_topic_aria', { topic: topic.topic })"
                                    @click="openEditDialog(topic)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="t('agents.topics.manage.delete_topic_aria', { topic: topic.topic })"
                                    @click="openDeleteDialog(topic)"
                                >
                                    <Trash2 class="text-destructive h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button variant="outline" size="sm" as-child>
                    <a :href="topicsAnalytics.url(props.agent.external_id)"> {{ t('agents.topics.manage.done') }} </a>
                </Button>
            </div>
        </div>

        <TopicFormDialog
            v-model:open="isAddDialogOpen"
            v-model:topic="form.topic"
            v-model:topic-description="form.description"
            id-prefix="add"
            :title="t('agents.topics.manage.add_dialog_title')"
            :description="t('agents.topics.manage.add_dialog_description')"
            :submit-label="t('agents.topics.manage.add_topic_button')"
            :submitting="isSubmitting"
            @submit="handleAdd"
        />

        <TopicFormDialog
            v-model:open="isEditDialogOpen"
            v-model:topic="form.topic"
            v-model:topic-description="form.description"
            id-prefix="edit"
            :title="t('agents.topics.manage.edit_dialog_title')"
            :description="t('agents.topics.manage.edit_dialog_description')"
            :submit-label="t('agents.topics.manage.save')"
            :submitting="isSubmitting"
            @submit="handleEdit"
        />

        <!-- Delete Topic confirmation. The confirm button is a plain Button (not
             AlertDialogAction) so the dialog stays open with its spinner until
             the request is done. -->
        <AlertDialog v-model:open="isDeleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.topics.manage.delete_dialog_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('agents.topics.manage.delete_dialog_description', { topic: currentTopic?.topic }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="isSubmitting">
                        {{ t('agents.topics.manage.cancel') }}
                    </AlertDialogCancel>
                    <Button variant="destructive" @click="handleDelete" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                        {{ t('agents.topics.manage.delete') }}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
