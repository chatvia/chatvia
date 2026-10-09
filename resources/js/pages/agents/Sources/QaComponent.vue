<script setup lang="ts">
/**
 * The agent's Q&A entries: curated answers to key questions. Lists the
 * entries and lets the user add, edit and delete them. An entry needs no
 * training, so there are no live updates here.
 */
import type { QaEntry } from '@/api/qaSources';
import DeleteSourceDialog from '@/components/agents/sources/DeleteSourceDialog.vue';
import QaEntryCard from '@/components/agents/sources/qa/QaEntryCard.vue';
import QaEntryForm from '@/components/agents/sources/qa/QaEntryForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useQaEntries } from '@/composables/agents/sources/useQaEntries';
import { HelpCircle, LoaderCircle, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

/** The open create/edit form: the entry it edits (null for a new one). */
interface OpenForm {
    entry: QaEntry | null;
    /** Changes on every open, so each open starts with a fresh form. */
    key: number;
}

const { t } = useI18n();

const { entries, isLoading, fetchEntries, fetchEntry, isDeleteDialogOpen, isDeleting, confirmDelete, deleteEntry } = useQaEntries(
    props.agent.external_id,
);

const openForm = ref<OpenForm | null>(null);
let formKey = 0;

function openCreateForm(): void {
    openForm.value = { entry: null, key: ++formKey };
}

async function openEditForm(entry: QaEntry): Promise<void> {
    const loaded = await fetchEntry(entry);

    if (loaded) {
        openForm.value = { entry: loaded, key: ++formKey };
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
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <HelpCircle class="h-5 w-5" />
                                {{ t('agents.sources.qa.title') }}
                            </CardTitle>
                            <CardDescription>{{ t('agents.sources.qa.description') }}</CardDescription>
                        </div>
                        <Button size="sm" @click="openCreateForm">
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('agents.sources.qa.add') }}
                        </Button>
                    </div>
                </CardHeader>
            </Card>

            <QaEntryForm
                v-if="openForm"
                :key="openForm.key"
                :agent-id="agent.external_id"
                :entry="openForm.entry"
                @saved="onSaved"
                @cancel="closeForm"
            />

            <Card v-if="isLoading && entries.length === 0">
                <CardContent class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                </CardContent>
            </Card>

            <Card v-else-if="entries.length === 0 && !openForm">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <HelpCircle class="text-muted-foreground mb-4 h-12 w-12" />
                    <h3 class="mb-2 text-lg font-medium">{{ t('agents.sources.qa.empty_title') }}</h3>
                    <p class="text-muted-foreground mb-4 max-w-sm">
                        {{ t('agents.sources.qa.empty_description') }}
                    </p>
                    <Button @click="openCreateForm">
                        <Plus class="me-2 h-4 w-4" />
                        {{ t('agents.sources.qa.add_first') }}
                    </Button>
                </CardContent>
            </Card>

            <div v-else class="space-y-4">
                <QaEntryCard v-for="entry in entries" :key="entry.id" :entry="entry" @edit="openEditForm(entry)" @delete="confirmDelete(entry)" />
            </div>

            <!-- A Q&A entry belongs to one agent, so the dialog never shows the "shared" wording. -->
            <DeleteSourceDialog
                v-model:open="isDeleteDialogOpen"
                :title="t('agents.sources.qa.delete_title')"
                :description="t('agents.sources.qa.delete_description')"
                shared-prefix=""
                shared-suffix=""
                :is-deleting="isDeleting"
                @delete="deleteEntry"
            />
        </div>
    </div>
</template>
