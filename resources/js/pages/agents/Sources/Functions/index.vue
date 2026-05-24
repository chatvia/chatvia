<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Switch } from '@/components/ui/switch';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { AgentFunction } from '@/types/agent-function';
import axios from 'axios';
import { AlertCircle, FunctionSquare, LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import FunctionEditor from './FunctionEditor.vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

const props = defineProps<Props>();

const loading = ref(true);
const functions = ref<AgentFunction[]>([]);
const editorOpen = ref(false);
const editing = ref<AgentFunction | null>(null);
const deleteTarget = ref<AgentFunction | null>(null);

async function fetchFunctions(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get(route('agents.sources.functions.data', props.agent.external_id));
        functions.value = response.data.data;
    } catch (err) {
        console.error(err);
        toast.error('Failed to load functions');
    } finally {
        loading.value = false;
    }
}

function openCreate(): void {
    editing.value = null;
    editorOpen.value = true;
}

function openEdit(fn: AgentFunction): void {
    editing.value = fn;
    editorOpen.value = true;
}

async function toggle(fn: AgentFunction): Promise<void> {
    try {
        const response = await axios.post(route('agents.sources.functions.toggle', { agent: props.agent.external_id, function: fn.external_id }));
        const updated: AgentFunction = response.data.data;
        const i = functions.value.findIndex((f) => f.external_id === fn.external_id);
        if (i !== -1) functions.value[i] = updated;
    } catch (err) {
        console.error(err);
        toast.error('Failed to toggle function');
    }
}

async function confirmDelete(): Promise<void> {
    const target = deleteTarget.value;
    if (!target) return;
    try {
        await axios.delete(route('agents.sources.functions.delete', { agent: props.agent.external_id, function: target.external_id }));
        functions.value = functions.value.filter((f) => f.external_id !== target.external_id);
        toast.success(`Deleted ${target.name}`);
    } catch (err) {
        console.error(err);
        toast.error('Failed to delete function');
    } finally {
        deleteTarget.value = null;
    }
}

function onSaved(fn: AgentFunction): void {
    const i = functions.value.findIndex((f) => f.external_id === fn.external_id);
    if (i === -1) {
        functions.value.unshift(fn);
    } else {
        functions.value[i] = fn;
    }
    editorOpen.value = false;
    editing.value = null;
}

onMounted(fetchFunctions);
</script>

<template>
    <AgentLayout :agent="agent">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Functions</h1>
                    <p class="text-muted-foreground text-sm">
                        Let the chat call your own APIs as tools. Define a function with a JSON-Schema for its arguments and a webhook URL — when a
                        user asks something the chat thinks needs your data, it will call your webhook and use the result in its reply.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="mr-1 h-4 w-4" />
                    New function
                </Button>
            </div>

            <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                <LoaderCircle class="h-4 w-4 animate-spin" />
                Loading functions
            </div>

            <Card v-else-if="functions.length === 0">
                <CardContent class="py-12 text-center">
                    <FunctionSquare class="mx-auto mb-3 h-10 w-10 opacity-40" />
                    <p class="font-medium">No functions yet</p>
                    <p class="text-muted-foreground mt-1 text-sm">Create a function the chat can call when it needs live data.</p>
                </CardContent>
            </Card>

            <div v-else class="grid gap-3">
                <Card v-for="fn in functions" :key="fn.external_id">
                    <CardContent class="flex items-center justify-between gap-4 py-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <code class="bg-muted rounded px-1.5 py-0.5 text-xs font-semibold">{{ fn.name }}</code>
                                <Badge v-if="!fn.enabled" variant="secondary">disabled</Badge>
                                <Badge v-if="fn.last_error" variant="destructive" class="gap-1">
                                    <AlertCircle class="h-3 w-3" />
                                    last call failed
                                </Badge>
                            </div>
                            <p class="text-muted-foreground mt-1 line-clamp-1 text-sm">{{ fn.description }}</p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ fn.webhook_method }} {{ fn.webhook_url }}
                                <span v-if="fn.invocation_count > 0"> · {{ fn.invocation_count }} calls</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Switch :model-value="fn.enabled" @update:model-value="toggle(fn)" />
                            <Button variant="ghost" size="icon" @click="openEdit(fn)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="text-destructive" @click="deleteTarget = fn">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <FunctionEditor v-model:open="editorOpen" :agent-id="agent.external_id" :existing="editing" @saved="onSaved" />

        <DeleteConfirmationDialog
            :open="!!deleteTarget"
            :title="`Delete function ${deleteTarget?.name ?? ''}?`"
            description="The chat will no longer be able to call this function. This cannot be undone."
            @cancel="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </AgentLayout>
</template>
