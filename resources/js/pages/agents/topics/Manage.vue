<script lang="ts" setup>
import LockedFeature from '@/components/LockedFeature.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import axios from 'axios';
import { LoaderCircle, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

// Permission checks
const { hasPermission } = useTeamPermissions();
const canManageTopics = computed(() => hasPermission('agent.topics.manage'));

// Plan capability
const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('topics'));

const loading = ref(true);
const topics = ref<any[]>([]);
const searchQuery = ref('');
const isAddDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const isFrozen = ref(false);
const isSubmitting = ref(false);

const currentTopic = ref<any>(null);
const form = ref({
    topic: '',
    description: '',
});

const filteredTopics = computed(() => {
    if (!searchQuery.value) return topics.value;
    return topics.value.filter((topic: any) => topic.topic.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const fetchTopics = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('agents.topics.index', props.agent.external_id));
        topics.value = response.data;

        // Set frozen status from first topic (assuming all have same status)
        if (topics.value.length > 0) {
            isFrozen.value = topics.value[0].is_frozen;
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('agents.topics.manage.load_error'));
    } finally {
        loading.value = false;
    }
};

const openAddDialog = () => {
    if (!canManageTopics.value) {
        toast.error(t('agents.topics.manage.permission_denied'), {
            description: t('agents.topics.manage.permission_denied_description'),
        });
        return;
    }

    form.value = {
        topic: '',
        description: '',
    };
    isAddDialogOpen.value = true;
};

const openEditDialog = (topic: any) => {
    if (!canManageTopics.value) {
        toast.error(t('agents.topics.manage.permission_denied'), {
            description: t('agents.topics.manage.permission_denied_description'),
        });
        return;
    }

    currentTopic.value = topic;
    form.value = {
        topic: topic.topic,
        description: topic.description,
    };
    isEditDialogOpen.value = true;
};

const openDeleteDialog = (topic: any) => {
    if (!canManageTopics.value) {
        toast.error(t('agents.topics.manage.permission_denied'), {
            description: t('agents.topics.manage.permission_denied_description'),
        });
        return;
    }

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
        await axios.post(route('agents.topics.store', props.agent.external_id), form.value);
        toast.success(t('agents.topics.manage.created'));
        isAddDialogOpen.value = false;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('agents.topics.manage.create_error'));
    } finally {
        isSubmitting.value = false;
    }
};

const handleEdit = async () => {
    if (!form.value.topic || !form.value.description) {
        toast.error(t('agents.topics.manage.fill_all_fields'));
        return;
    }

    isSubmitting.value = true;
    try {
        await axios.patch(route('agents.topics.update', [props.agent.external_id, currentTopic.value.id]), form.value);
        toast.success(t('agents.topics.manage.updated'));
        isEditDialogOpen.value = false;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('agents.topics.manage.update_error'));
    } finally {
        isSubmitting.value = false;
    }
};

const handleDelete = async () => {
    isSubmitting.value = true;
    try {
        await axios.delete(route('agents.topics.destroy', [props.agent.external_id, currentTopic.value.id]));
        toast.success(t('agents.topics.manage.deleted'));
        isDeleteDialogOpen.value = false;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('agents.topics.manage.delete_error'));
    } finally {
        isSubmitting.value = false;
    }
};

const handleToggleFreeze = async (value: boolean) => {
    try {
        await axios.post(route('agents.topics.toggle-freeze', props.agent.external_id), {
            is_frozen: value,
        });
        toast.success(value ? t('agents.topics.manage.frozen') : t('agents.topics.manage.unfrozen'));
        isFrozen.value = value;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('agents.topics.manage.freeze_error'));
        // Revert the switch
        isFrozen.value = !value;
    }
};

onMounted(() => {
    fetchTopics();
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.topics.manage.title')" :description="t('agents.topics.manage.description')">
        <template #header-icon>
            <Plus class="h-5 w-5" />
        </template>

        <LockedFeature v-if="!hasAccess" :description="t('agents.topics.manage.locked_description')" />

        <div v-else class="space-y-6">
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
                                <Button variant="ghost" size="icon" @click="openEditDialog(topic)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="openDeleteDialog(topic)">
                                    <Trash2 class="text-destructive h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Freeze Topics -->
            <Card>
                <CardContent class="!p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Switch :checked="isFrozen" @update:checked="handleToggleFreeze" />
                            <div>
                                <Label class="cursor-pointer">{{ t('agents.topics.manage.freeze_topics') }}</Label>
                                <p class="text-muted-foreground text-sm">{{ t('agents.topics.manage.freeze_hint') }}</p>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <a :href="route('agents.analytics.topics', props.agent.external_id)"> {{ t('agents.topics.manage.done') }} </a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Add Topic Dialog -->
        <Dialog v-model:open="isAddDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.topics.manage.add_dialog_title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.topics.manage.add_dialog_description') }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="add-topic">{{ t('agents.topics.manage.topic_name') }}</Label>
                        <Input
                            id="add-topic"
                            v-model="form.topic"
                            :placeholder="t('agents.topics.manage.topic_name_placeholder')"
                            @keydown.enter="handleAdd"
                        />
                    </div>
                    <div>
                        <Label for="add-description">{{ t('agents.topics.manage.description_label') }}</Label>
                        <Textarea
                            id="add-description"
                            v-model="form.description"
                            :placeholder="t('agents.topics.manage.description_placeholder')"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isAddDialogOpen = false" :disabled="isSubmitting">
                        {{ t('agents.topics.manage.cancel') }}
                    </Button>
                    <Button @click="handleAdd" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                        {{ t('agents.topics.manage.add_topic_button') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Topic Dialog -->
        <Dialog v-model:open="isEditDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.topics.manage.edit_dialog_title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.topics.manage.edit_dialog_description') }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="edit-topic">{{ t('agents.topics.manage.topic_name') }}</Label>
                        <Input
                            id="edit-topic"
                            v-model="form.topic"
                            :placeholder="t('agents.topics.manage.topic_name_placeholder')"
                            @keydown.enter="handleEdit"
                        />
                    </div>
                    <div>
                        <Label for="edit-description">{{ t('agents.topics.manage.description_label') }}</Label>
                        <Textarea
                            id="edit-description"
                            v-model="form.description"
                            :placeholder="t('agents.topics.manage.description_placeholder')"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isEditDialogOpen = false" :disabled="isSubmitting">
                        {{ t('agents.topics.manage.cancel') }}
                    </Button>
                    <Button @click="handleEdit" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                        {{ t('agents.topics.manage.save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Topic Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.topics.manage.delete_dialog_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.topics.manage.delete_dialog_description', { topic: currentTopic?.topic }) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false" :disabled="isSubmitting">
                        {{ t('agents.topics.manage.cancel') }}
                    </Button>
                    <Button variant="destructive" @click="handleDelete" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                        {{ t('agents.topics.manage.delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
