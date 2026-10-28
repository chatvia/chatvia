<script setup lang="ts">
/**
 * Topic manager of the Topics page: search every topic the agent may use,
 * attach or remove one, or create a new one. Loads the list each time it opens
 * and forgets the search and form when it closes.
 */
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { ChevronLeft, ChevronRight, LoaderCircle, Plus, Search, X } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTopicLibrary } from './useTopicLibrary';

const props = defineProps<{
    agentId: string;
    /** Reloads the page's topic charts after a change. */
    reloadTrends: () => Promise<void>;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const {
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
} = useTopicLibrary(() => props.agentId, props.reloadTrends);

watch(open, (isOpen) => {
    if (isOpen) {
        fetchAllTopics();
    } else {
        reset();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[80vh] max-w-2xl flex-col">
            <DialogHeader>
                <DialogTitle>{{ t('agents.topics_index.manage_title') }}</DialogTitle>
                <DialogDescription>{{ t('agents.topics_index.manage_description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex flex-1 flex-col space-y-4 overflow-hidden">
                <div class="relative">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
                    <Input v-model="searchQuery" :placeholder="t('agents.topics_index.search_placeholder')" class="ps-10" />
                </div>

                <div class="space-y-3">
                    <Button v-if="!showAddForm" @click="showAddForm = true" variant="outline" size="sm" class="w-full">
                        <Plus class="me-2 h-4 w-4" />
                        {{ t('agents.topics_index.add_new') }}
                    </Button>

                    <div v-else class="space-y-3 rounded-lg border p-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ t('agents.topics_index.topic_name') }}</label>
                            <Input v-model="newTopicName" :placeholder="t('agents.topics_index.topic_name_placeholder')" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ t('agents.topics_index.description_label') }}</label>
                            <Input v-model="newTopicDescription" :placeholder="t('agents.topics_index.description_placeholder')" />
                        </div>
                        <div class="flex gap-2">
                            <Button
                                @click="createNewTopic"
                                :disabled="isAddingTopic || !newTopicName.trim() || !newTopicDescription.trim()"
                                size="sm"
                                class="flex-1"
                            >
                                <LoaderCircle v-if="isAddingTopic" class="me-2 h-4 w-4 animate-spin" />
                                {{ t('agents.topics_index.create_add') }}
                            </Button>
                            <Button @click="showAddForm = false" variant="outline" size="sm">{{ t('common.cancel') }}</Button>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto rounded-lg border">
                    <div v-if="loadingTopics" class="flex items-center justify-center py-12">
                        <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                    </div>

                    <div v-else-if="filteredTopics.length === 0" class="text-muted-foreground py-12 text-center">
                        <p>{{ t('agents.topics_index.no_topics_found') }}</p>
                    </div>

                    <div v-else class="divide-y">
                        <div v-for="topic in paginatedTopics" :key="topic.id" class="hover:bg-muted/50 p-4 transition-colors">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-medium">{{ topic.topic }}</h4>
                                        <Badge v-if="isTopicAssociated(topic.id)" variant="secondary" class="text-xs">{{
                                            t('agents.topics_index.active')
                                        }}</Badge>
                                    </div>
                                    <p class="text-muted-foreground mt-1 text-sm">{{ topic.description }}</p>
                                </div>

                                <Button
                                    v-if="isTopicAssociated(topic.id)"
                                    @click="removeTopicFromAgent(topic.id)"
                                    variant="outline"
                                    size="sm"
                                    class="shrink-0"
                                >
                                    <X class="me-1 h-4 w-4" />
                                    {{ t('agents.topics_index.remove') }}
                                </Button>
                                <Button v-else @click="addTopicToAgent(topic.id)" variant="default" size="sm" class="shrink-0">
                                    <Plus class="me-1 h-4 w-4" />
                                    {{ t('agents.topics_index.add') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="totalPages > 1" class="flex items-center justify-between border-t px-4 py-3">
                    <div class="text-muted-foreground text-sm">
                        {{ t('agents.topics_index.page_of', { current: currentPage, last: totalPages, total: filteredTopics.length }) }}
                    </div>
                    <div class="flex gap-2">
                        <Button
                            @click="currentPage--"
                            :disabled="currentPage === 1"
                            :aria-label="t('agents.topics_index.previous_page')"
                            variant="outline"
                            size="sm"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button
                            @click="currentPage++"
                            :disabled="currentPage === totalPages"
                            :aria-label="t('agents.topics_index.next_page')"
                            variant="outline"
                            size="sm"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button @click="open = false" variant="outline">{{ t('agents.topics_index.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
