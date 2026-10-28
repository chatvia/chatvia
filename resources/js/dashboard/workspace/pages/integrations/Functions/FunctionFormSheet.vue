<script setup lang="ts">
/**
 * Create / edit sheet for a workspace function (a webhook the agents can call
 * as a tool). Editing adds an Agents tab to choose which agents may use it,
 * and a Delete action behind a confirmation.
 */
import AgentAttachmentList from '@/dashboard/workspace/components/AgentAttachmentList.vue';
import CopyIdField from '@/dashboard/workspace/components/CopyIdField.vue';
import DeleteConfirmationDialog from '@/dashboard/components/DeleteConfirmationDialog.vue';
import JsonCodeEditor from '@/dashboard/workspace/components/JsonCodeEditor.vue';
import InputError from '@/shared/components/InputError.vue';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/shared/ui/sheet';
import Switch from '@/shared/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import { useAgentAttachments } from '@/dashboard/workspace/composables/useAgentAttachments';
import { destroy as destroyFunction, store as storeFunction, update as updateFunction } from '@/routes/integrations/functions';
import { router, useForm } from '@inertiajs/vue3';
import { AlignLeft, ExternalLink, Hash, LoaderCircle, Save, Send, Trash2, Webhook } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FunctionAuthFields from './FunctionAuthFields.vue';

export type FunctionWebhookMethod = 'POST' | 'GET' | 'PATCH' | 'DELETE';
export type FunctionAuthType = 'none' | 'bearer' | 'header' | 'hmac';

export interface FunctionRow {
    id: string;
    internal_name: string;
    display_name: string | null;
    description: string;
    parameters_schema: string;
    webhook_url: string;
    webhook_method: FunctionWebhookMethod;
    auth_type: FunctionAuthType;
    auth_header_name: string;
    has_auth_value: boolean;
    is_enabled: boolean;
    requires_approval: boolean;
    created_at: string | null;
    agents_count: number;
}

interface Props {
    open: boolean;
    func: FunctionRow | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean]; changed: [] }>();

const { t } = useI18n();

const isEdit = computed(() => props.func !== null);

const activeTab = ref<'edit' | 'agents'>('edit');
const agents = useAgentAttachments('function');
const agentsLoaded = ref(false);

const exampleSchema = `{
  "type": "object",
  "properties": {
    "order_id": { "type": "string" }
  },
  "required": ["order_id"]
}`;

const form = useForm({
    internal_name: '',
    display_name: '',
    description: '',
    parameters_schema: exampleSchema,
    webhook_url: '',
    webhook_method: 'POST' as FunctionWebhookMethod,
    auth_type: 'none' as FunctionAuthType,
    auth_header_name: '',
    auth_value: '',
    requires_approval: true,
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        activeTab.value = 'edit';
        agentsLoaded.value = false;
        agents.reset();
        if (props.func) {
            form.internal_name = props.func.internal_name;
            form.display_name = props.func.display_name ?? '';
            form.description = props.func.description;
            form.parameters_schema = props.func.parameters_schema;
            form.webhook_url = props.func.webhook_url;
            form.webhook_method = props.func.webhook_method;
            form.auth_type = props.func.auth_type;
            form.auth_header_name = props.func.auth_header_name ?? '';
            form.auth_value = '';
            form.requires_approval = props.func.requires_approval;
        } else {
            form.reset();
            form.parameters_schema = exampleSchema;
            form.webhook_method = 'POST';
            form.auth_type = 'none';
            form.requires_approval = true;
        }
    },
);

// Lazy-load the agent lists the first time the user opens Agents/Attach.
watch(activeTab, (tab) => {
    if (tab === 'agents' && props.func && !agentsLoaded.value && !agents.loading.value) {
        agentsLoaded.value = true;
        agents.load(props.func.id);
    }
});

// Switching auth type only clears stale validation errors — values are kept so
// prefilled edits aren't wiped, and the irrelevant fields are hidden by v-if.
watch(
    () => form.auth_type,
    () => form.clearErrors('auth_header_name', 'auth_value'),
);

function submit(): void {
    if (props.func) {
        form.patch(updateFunction.url(props.func.id), {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
        });
    } else {
        form.post(storeFunction.url(), {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
        });
    }
}

async function onToggleAttachment(agentId: string): Promise<void> {
    if (!props.func) return;
    await agents.toggleAttachment(props.func.id, agentId);
    emit('changed');
}

const deleteOpen = ref(false);

function askDelete(): void {
    // Sheet + Dialog at the same layer overlap awkwardly; close the sheet first.
    emit('update:open', false);
    setTimeout(() => (deleteOpen.value = true), 150);
}

function confirmDelete(): void {
    if (!props.func) return;
    router.delete(destroyFunction.url(props.func.id), { preserveScroll: true });
    deleteOpen.value = false;
}

const keepSecretHint = computed(() => isEdit.value && props.func?.has_auth_value === true);
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent class="w-full overflow-y-auto p-0 sm:max-w-xl">
            <SheetHeader class="border-b p-6">
                <SheetTitle>{{ isEdit ? t('workspace.functions.sheet.edit_title') : t('workspace.functions.sheet.create_title') }}</SheetTitle>
                <SheetDescription>
                    <i18n-t keypath="workspace.functions.sheet.description" tag="span">
                        <template #name><code class="text-xs">name</code></template>
                        <template #description><code class="text-xs">description</code></template>
                        <template #learn_more>
                            <a
                                href="https://platform.openai.com/docs/guides/function-calling"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-foreground inline-flex items-center gap-1 underline underline-offset-2"
                            >
                                {{ t('workspace.functions.sheet.learn_more') }}
                                <ExternalLink class="size-3" />
                            </a>
                        </template>
                    </i18n-t>
                </SheetDescription>
            </SheetHeader>

            <Tabs v-model="activeTab" class="px-6 pt-5">
                <TabsList v-if="isEdit" class="grid w-full grid-cols-2">
                    <TabsTrigger value="edit">{{ t('workspace.functions.tabs.edit') }}</TabsTrigger>
                    <TabsTrigger value="agents">{{ t('workspace.functions.tabs.agents') }}</TabsTrigger>
                </TabsList>

                <TabsContent value="edit" class="pt-6">
                    <form @submit.prevent="submit" class="space-y-5">
                        <CopyIdField
                            v-if="isEdit && func"
                            :label="t('workspace.functions.sheet.id_label')"
                            :value="func.id"
                            :copy-title="t('workspace.functions.sheet.copy_id_title')"
                            :copy-label="t('workspace.functions.sheet.copy_id')"
                        />

                        <div class="grid gap-2">
                            <Label for="internal_name">{{ t('workspace.functions.form.name') }}</Label>
                            <div class="relative">
                                <Hash class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                                <Input id="internal_name" v-model="form.internal_name" placeholder="get_order_status" class="ps-9" required />
                            </div>
                            <p class="text-muted-foreground text-xs">{{ t('workspace.functions.form.name_hint') }}</p>
                            <InputError :message="form.errors.internal_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="display_name"
                                >{{ t('workspace.functions.form.display_name') }}
                                <span class="text-muted-foreground font-normal">{{ t('workspace.functions.form.optional') }}</span></Label
                            >
                            <div class="relative">
                                <Hash class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                                <Input
                                    id="display_name"
                                    v-model="form.display_name"
                                    :placeholder="t('workspace.functions.form.display_name_placeholder')"
                                    class="ps-9"
                                />
                            </div>
                            <p class="text-muted-foreground text-xs">{{ t('workspace.functions.form.display_name_hint') }}</p>
                            <InputError :message="form.errors.display_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">{{ t('workspace.functions.form.description') }}</Label>
                            <div class="relative">
                                <AlignLeft class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                                <Input
                                    id="description"
                                    v-model="form.description"
                                    :placeholder="t('workspace.functions.form.description_placeholder')"
                                    class="ps-9"
                                    required
                                />
                            </div>
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('workspace.functions.form.parameters') }}</Label>
                            <JsonCodeEditor v-model="form.parameters_schema" :rows="10" :aria-label="t('workspace.functions.form.parameters_aria')" />
                            <InputError :message="form.errors.parameters_schema" />
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2 grid gap-2">
                                <Label for="webhook_url">{{ t('workspace.functions.form.webhook_url') }}</Label>
                                <div class="relative">
                                    <Webhook class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                                    <Input
                                        id="webhook_url"
                                        v-model="form.webhook_url"
                                        type="url"
                                        placeholder="https://example.com/webhook"
                                        class="ps-9"
                                        required
                                    />
                                </div>
                                <InputError :message="form.errors.webhook_url" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="webhook_method">{{ t('workspace.functions.form.method') }}</Label>
                                <Select v-model="form.webhook_method">
                                    <SelectTrigger id="webhook_method" class="w-full">
                                        <span class="flex min-w-0 items-center gap-2">
                                            <Send class="text-muted-foreground size-4" />
                                            <SelectValue />
                                        </span>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="POST">POST</SelectItem>
                                        <SelectItem value="GET">GET</SelectItem>
                                        <SelectItem value="PATCH">PATCH</SelectItem>
                                        <SelectItem value="DELETE">DELETE</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <!-- Human-in-the-loop: pause for the user's confirmation before
                             this function runs. Seeded from the method (writes default on). -->
                        <div class="flex items-start justify-between gap-3 rounded-lg border p-3">
                            <div class="min-w-0">
                                <Label class="text-sm font-medium">{{ t('workspace.functions.form.require_approval') }}</Label>
                                <p class="text-muted-foreground mt-0.5 text-xs">
                                    {{ t('workspace.functions.form.require_approval_hint') }}
                                </p>
                            </div>
                            <Switch :model-value="form.requires_approval" @update:model-value="(v) => (form.requires_approval = v)" />
                        </div>

                        <FunctionAuthFields
                            v-model:auth-type="form.auth_type"
                            v-model:header-name="form.auth_header_name"
                            v-model:auth-value="form.auth_value"
                            :keep-secret-hint="keepSecretHint"
                            :header-name-error="form.errors.auth_header_name"
                            :value-error="form.errors.auth_value"
                        />

                        <div class="flex items-center justify-end gap-2 border-t pt-5">
                            <Button
                                v-if="isEdit"
                                type="button"
                                variant="ghost"
                                class="text-destructive hover:text-destructive me-auto"
                                @click="askDelete"
                            >
                                <Trash2 class="me-2 size-4" />
                                {{ t('workspace.common.delete') }}
                            </Button>
                            <Button type="button" variant="outline" @click="emit('update:open', false)">{{ t('workspace.common.cancel') }}</Button>
                            <Button type="submit" :disabled="form.processing">
                                <LoaderCircle v-if="form.processing" class="me-2 size-4 animate-spin" />
                                <Save v-else class="me-2 size-4" />
                                {{ isEdit ? t('workspace.common.save') : t('workspace.functions.add') }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <TabsContent v-if="isEdit" value="agents" class="pt-6">
                    <AgentAttachmentList
                        :intro="t('workspace.functions.agents.intro')"
                        :loading-text="t('workspace.functions.agents.loading')"
                        :empty-text="t('workspace.functions.agents.empty')"
                        :agents="agents.all.value"
                        :loading="agents.loading.value"
                        :busy-id="agents.busyId.value"
                        @toggle="onToggleAttachment"
                    />
                </TabsContent>
            </Tabs>

            <div class="h-6"></div>
        </SheetContent>
    </Sheet>

    <DeleteConfirmationDialog
        :open="deleteOpen"
        :title="t('workspace.functions.delete_confirm_title', { name: func?.internal_name ?? t('workspace.functions.fallback_name') })"
        :description="t('workspace.functions.delete_confirm_description')"
        :confirm-text="t('workspace.common.delete')"
        :cancel-text="t('workspace.common.keep')"
        @cancel="deleteOpen = false"
        @confirm="confirmDelete"
    />
</template>
