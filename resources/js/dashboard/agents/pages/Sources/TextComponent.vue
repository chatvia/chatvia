<script setup lang="ts">
/**
 * The agent's Text sources: write texts for the knowledge base, edit them,
 * attach texts the workspace already trained, and unlink or delete them.
 * Statuses and training progress update live (see useTextSources).
 */
import type { TextSource } from '@/dashboard/agents/api/textSources';
import DeleteSourceDialog from '@/dashboard/agents/components/sources/DeleteSourceDialog.vue';
import TextEntryForm from '@/dashboard/agents/components/sources/text/TextEntryForm.vue';
import TextEntryList from '@/dashboard/agents/components/sources/text/TextEntryList.vue';
import TextSourcesHeader from '@/dashboard/agents/components/sources/text/TextSourcesHeader.vue';
import AttachTextDialog from '@/dashboard/agents/components/AttachTextDialog.vue';
import { Button } from '@/shared/ui/button';
import { Card, CardContent } from '@/shared/ui/card';
import { useTextSourceRemoval } from '@/dashboard/agents/composables/sources/useTextSourceRemoval';
import { useTextSources } from '@/dashboard/agents/composables/sources/useTextSources';
import { LoaderCircle, Plus, Type } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

/** The open create/edit form: the text it edits (null for a new one) and its starting content. */
interface OpenForm {
    entryId: number | null;
    content: string;
    /** Changes on every open, so each open starts with a fresh form. */
    key: number;
}

const { t } = useI18n();

const { entries, total, hasMore, isLoadingMore, isLoading, fetchEntries, loadMore, fetchContent } = useTextSources(props.agent.external_id);
const { isDialogOpen, target, isUnlinking, isDeleting, confirmRemove, unlink, remove } = useTextSourceRemoval(props.agent.external_id, fetchEntries);

const openForm = ref<OpenForm | null>(null);
const isAttachDialogOpen = ref(false);
let formKey = 0;

function openCreateForm(): void {
    openForm.value = { entryId: null, content: '', key: ++formKey };
}

async function openEditForm(entry: TextSource): Promise<void> {
    const content = await fetchContent(entry);

    if (content !== null) {
        openForm.value = { entryId: entry.id, content, key: ++formKey };
    }
}

function closeForm(): void {
    openForm.value = null;
}

function onSaved(): void {
    closeForm();
    fetchEntries();
}

fetchEntries();
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <TextSourcesHeader @create="openCreateForm" @attach="isAttachDialogOpen = true" />

            <TextEntryForm
                v-if="openForm"
                :key="openForm.key"
                :agent-id="agent.external_id"
                :entry-id="openForm.entryId"
                :initial-content="openForm.content"
                @saved="onSaved"
                @cancel="closeForm"
            />

            <TextEntryList
                v-if="!isLoading && entries.length > 0"
                :entries="entries"
                :total="total"
                :has-more="hasMore"
                :is-loading-more="isLoadingMore"
                @load-more="loadMore"
                @edit="openEditForm"
                @remove="confirmRemove"
            />

            <Card v-else-if="!isLoading && entries.length === 0 && !openForm">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <Type class="text-muted-foreground mb-4 h-16 w-16" />
                    <h3 class="mb-2 text-lg font-medium">{{ t('agents.sources.text.empty_title') }}</h3>
                    <p class="text-muted-foreground mb-6 max-w-md text-center">
                        {{ t('agents.sources.text.empty_description') }}
                    </p>
                    <Button class="flex items-center gap-2" @click="openCreateForm">
                        <Plus class="h-4 w-4" />
                        {{ t('agents.sources.text.create_first') }}
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="isLoading">
                <CardContent class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-primary h-8 w-8 animate-spin" />
                    <span class="ms-2 text-lg">{{ t('agents.sources.text.loading') }}</span>
                </CardContent>
            </Card>

            <DeleteSourceDialog
                v-if="target"
                v-model:open="isDialogOpen"
                :title="`${target.excerpt.substring(0, 100)}...`"
                :agents-count="target.agents_count"
                :description="t('agents.sources.text.delete_description')"
                :shared-prefix="t('agents.sources.text.delete_shared_prefix')"
                :shared-suffix="t('agents.sources.text.delete_shared_suffix')"
                :is-unlinking="isUnlinking"
                :is-deleting="isDeleting"
                @unlink="unlink"
                @delete="remove"
            />

            <AttachTextDialog v-model:open="isAttachDialogOpen" :agent-id="agent.external_id" @text-attached="fetchEntries" />
        </div>
    </div>
</template>
