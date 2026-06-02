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
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
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
        toast.error(error.response?.data?.message || 'Failed to load topics');
    } finally {
        loading.value = false;
    }
};

const openAddDialog = () => {
    if (!canManageTopics.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to manage topics.',
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
        toast.error('Permission denied', {
            description: 'You do not have permission to manage topics.',
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
        toast.error('Permission denied', {
            description: 'You do not have permission to manage topics.',
        });
        return;
    }

    currentTopic.value = topic;
    isDeleteDialogOpen.value = true;
};

const handleAdd = async () => {
    if (!form.value.topic || !form.value.description) {
        toast.error('Please fill in all fields');
        return;
    }

    isSubmitting.value = true;
    try {
        await axios.post(route('agents.topics.store', props.agent.external_id), form.value);
        toast.success('Topic created successfully');
        isAddDialogOpen.value = false;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to create topic');
    } finally {
        isSubmitting.value = false;
    }
};

const handleEdit = async () => {
    if (!form.value.topic || !form.value.description) {
        toast.error('Please fill in all fields');
        return;
    }

    isSubmitting.value = true;
    try {
        await axios.patch(route('agents.topics.update', [props.agent.external_id, currentTopic.value.id]), form.value);
        toast.success('Topic updated successfully');
        isEditDialogOpen.value = false;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to update topic');
    } finally {
        isSubmitting.value = false;
    }
};

const handleDelete = async () => {
    isSubmitting.value = true;
    try {
        await axios.delete(route('agents.topics.destroy', [props.agent.external_id, currentTopic.value.id]));
        toast.success('Topic deleted successfully');
        isDeleteDialogOpen.value = false;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to delete topic');
    } finally {
        isSubmitting.value = false;
    }
};

const handleToggleFreeze = async (value: boolean) => {
    try {
        await axios.post(route('agents.topics.toggle-freeze', props.agent.external_id), {
            is_frozen: value,
        });
        toast.success(value ? 'Topics frozen successfully' : 'Topics unfrozen successfully');
        isFrozen.value = value;
        fetchTopics();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to update freeze status');
        // Revert the switch
        isFrozen.value = !value;
    }
};

onMounted(() => {
    fetchTopics();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Manage topics" description="Create and manage conversation topics for your agent">
        <template #header-icon>
            <Plus class="h-5 w-5" />
        </template>

        <LockedFeature v-if="!hasAccess" description="Upgrade your plan to manage conversation topics for this agent." />

        <div v-else class="space-y-6">
            <!-- Search and Add -->
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div class="relative w-full sm:w-96">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                    <Input v-model="searchQuery" placeholder="Search" class="pl-9" />
                </div>
                <Button @click="openAddDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Add topic
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
                        <p v-if="searchQuery">No topics found matching "{{ searchQuery }}"</p>
                        <p v-else>No topics yet. Click "Add topic" to create one.</p>
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
                            <div class="ml-4 flex items-center gap-2">
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
                                <Label class="cursor-pointer">Freeze Topics</Label>
                                <p class="text-muted-foreground text-sm">Prevent topics from being automatically updated</p>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <a :href="route('agents.analytics.topics', props.agent.external_id)"> Done </a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Add Topic Dialog -->
        <Dialog v-model:open="isAddDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Topic</DialogTitle>
                    <DialogDescription> Create a new conversation topic for this agent </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="add-topic">Topic Name</Label>
                        <Input id="add-topic" v-model="form.topic" placeholder="e.g., Account Subscription" @keydown.enter="handleAdd" />
                    </div>
                    <div>
                        <Label for="add-description">Description</Label>
                        <Textarea
                            id="add-description"
                            v-model="form.description"
                            placeholder="Brief description of what this topic covers"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isAddDialogOpen = false" :disabled="isSubmitting"> Cancel </Button>
                    <Button @click="handleAdd" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Add Topic
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Topic Dialog -->
        <Dialog v-model:open="isEditDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Topic</DialogTitle>
                    <DialogDescription> Update the conversation topic </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="edit-topic">Topic Name</Label>
                        <Input id="edit-topic" v-model="form.topic" placeholder="e.g., Account Subscription" @keydown.enter="handleEdit" />
                    </div>
                    <div>
                        <Label for="edit-description">Description</Label>
                        <Textarea
                            id="edit-description"
                            v-model="form.description"
                            placeholder="Brief description of what this topic covers"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isEditDialogOpen = false" :disabled="isSubmitting"> Cancel </Button>
                    <Button @click="handleEdit" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Topic Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Topic</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ currentTopic?.topic }}"? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false" :disabled="isSubmitting"> Cancel </Button>
                    <Button variant="destructive" @click="handleDelete" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
