<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Input } from '@/components/ui/input';
import JsonCodeEditor from '@/components/ui/JsonCodeEditor.vue';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import { router, useForm } from '@inertiajs/vue3';
import { AlignLeft, Check, Copy, ExternalLink, Hash, Key, KeyRound, LoaderCircle, Lock, Save, Send, Shield, Trash2, Webhook } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface FunctionRow {
    id: string;
    name: string;
    description: string;
    parameters_schema: string;
    webhook_url: string;
    webhook_method: 'POST' | 'GET' | 'PATCH' | 'DELETE';
    auth_type: 'none' | 'bearer' | 'header' | 'hmac';
    auth_header_name: string;
    has_auth_value: boolean;
    enabled: boolean;
    created_at: string | null;
    agents_count: number;
}

interface Props {
    open: boolean;
    func: FunctionRow | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean]; changed: [] }>();

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
    name: '',
    description: '',
    parameters_schema: exampleSchema,
    webhook_url: '',
    webhook_method: 'POST' as 'POST' | 'GET' | 'PATCH' | 'DELETE',
    auth_type: 'none' as 'none' | 'bearer' | 'header' | 'hmac',
    auth_header_name: '',
    auth_value: '',
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
            form.name = props.func.name;
            form.description = props.func.description;
            form.parameters_schema = props.func.parameters_schema;
            form.webhook_url = props.func.webhook_url;
            form.webhook_method = props.func.webhook_method;
            form.auth_type = props.func.auth_type;
            form.auth_header_name = props.func.auth_header_name ?? '';
            form.auth_value = '';
        } else {
            form.reset();
            form.parameters_schema = exampleSchema;
            form.webhook_method = 'POST';
            form.auth_type = 'none';
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
        form.patch(route('settings.functions.update', { external_id: props.func.id }), {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
        });
    } else {
        form.post(route('settings.functions.store'), {
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
    router.delete(route('settings.functions.destroy', { external_id: props.func.id }), { preserveScroll: true });
    deleteOpen.value = false;
}

const keepSecretHint = computed(() => isEdit.value && props.func?.has_auth_value === true);

const idCopied = ref(false);

async function copyId(): Promise<void> {
    if (!props.func) return;
    await navigator.clipboard.writeText(props.func.id);
    idCopied.value = true;
    setTimeout(() => (idCopied.value = false), 2000);
}
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent class="w-full overflow-y-auto p-0 sm:max-w-xl">
            <SheetHeader class="border-b p-6">
                <SheetTitle>{{ isEdit ? 'Edit function' : 'Add a function' }}</SheetTitle>
                <SheetDescription>
                    Functions let your agents call your own APIs as LLM tools. The <code class="text-xs">name</code> and
                    <code class="text-xs">description</code> are what the model sees when deciding whether to call it — be specific.
                    <a
                        href="https://platform.openai.com/docs/guides/function-calling"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-foreground inline-flex items-center gap-1 underline underline-offset-2"
                    >
                        Learn more
                        <ExternalLink class="size-3" />
                    </a>
                </SheetDescription>
            </SheetHeader>

            <Tabs v-model="activeTab" class="px-6 pt-5">
                <TabsList v-if="isEdit" class="grid w-full grid-cols-2">
                    <TabsTrigger value="edit">Edit</TabsTrigger>
                    <TabsTrigger value="agents">Agents</TabsTrigger>
                </TabsList>

                <TabsContent value="edit" class="pt-6">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div v-if="isEdit" class="bg-muted/40 flex items-center gap-2 rounded-md border px-3 py-2">
                            <span class="text-muted-foreground text-xs">ID</span>
                            <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="func?.id">{{ func?.id }}</code>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-6 shrink-0 px-1.5"
                                title="Copy function ID — pass as function_id when attaching via the API"
                                @click="copyId"
                            >
                                <Check v-if="idCopied" class="size-3.5 text-green-600" />
                                <Copy v-else class="size-3.5" />
                            </Button>
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <div class="relative">
                                <Hash class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input id="name" v-model="form.name" placeholder="get_order_status" class="pl-9" required />
                            </div>
                            <p class="text-muted-foreground text-xs">Letters, numbers, underscore. Must start with a letter.</p>
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <div class="relative">
                                <AlignLeft class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Look up the current status of a customer's order by id."
                                    class="pl-9"
                                    required
                                />
                            </div>
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Parameters (JSON schema)</Label>
                            <JsonCodeEditor v-model="form.parameters_schema" :rows="10" aria-label="Function parameters schema" />
                            <InputError :message="form.errors.parameters_schema" />
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2 grid gap-2">
                                <Label for="webhook_url">Webhook URL</Label>
                                <div class="relative">
                                    <Webhook class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="webhook_url"
                                        v-model="form.webhook_url"
                                        type="url"
                                        placeholder="https://example.com/webhook"
                                        class="pl-9"
                                        required
                                    />
                                </div>
                                <InputError :message="form.errors.webhook_url" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="webhook_method">Method</Label>
                                <div class="relative">
                                    <Send class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <select
                                        id="webhook_method"
                                        v-model="form.webhook_method"
                                        class="border-input bg-background h-9 w-full rounded-md border pr-3 pl-9 text-sm"
                                    >
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                        <option value="PATCH">PATCH</option>
                                        <option value="DELETE">DELETE</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="auth_type">Auth</Label>
                            <div class="relative">
                                <Shield class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <select
                                    id="auth_type"
                                    v-model="form.auth_type"
                                    class="border-input bg-background h-9 w-full rounded-md border pr-3 pl-9 text-sm"
                                >
                                    <option value="none">None — no auth header sent</option>
                                    <option value="bearer">Bearer token — sent as `Authorization: Bearer …`</option>
                                    <option value="header">Custom header — your own header name + value</option>
                                    <option value="hmac">HMAC — body signed with a shared secret</option>
                                </select>
                            </div>

                            <div v-if="form.auth_type === 'bearer'" class="mt-2 grid gap-2">
                                <Label for="auth_value">Bearer token</Label>
                                <div class="relative">
                                    <KeyRound class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="auth_value"
                                        v-model="form.auth_value"
                                        type="password"
                                        autocomplete="off"
                                        :placeholder="keepSecretHint ? 'Leave blank to keep current token' : 'sk-...'"
                                        class="pl-9"
                                    />
                                </div>
                                <p class="text-muted-foreground text-xs">Sent as <code class="text-xs">Authorization: Bearer &lt;token&gt;</code>.</p>
                                <InputError :message="form.errors.auth_value" />
                            </div>

                            <div v-else-if="form.auth_type === 'header'" class="mt-2 grid grid-cols-2 gap-3">
                                <div class="grid gap-2">
                                    <Label for="auth_header_name">Header name</Label>
                                    <div class="relative">
                                        <Key class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input id="auth_header_name" v-model="form.auth_header_name" placeholder="X-Api-Key" class="pl-9" />
                                    </div>
                                    <InputError :message="form.errors.auth_header_name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="auth_value">Header value</Label>
                                    <div class="relative">
                                        <Lock class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            id="auth_value"
                                            v-model="form.auth_value"
                                            type="password"
                                            autocomplete="off"
                                            :placeholder="keepSecretHint ? 'Leave blank to keep current' : ''"
                                            class="pl-9"
                                        />
                                    </div>
                                    <InputError :message="form.errors.auth_value" />
                                </div>
                            </div>

                            <div v-else-if="form.auth_type === 'hmac'" class="mt-2 grid gap-2">
                                <Label for="auth_value">Signing secret</Label>
                                <div class="relative">
                                    <Lock class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="auth_value"
                                        v-model="form.auth_value"
                                        type="password"
                                        autocomplete="off"
                                        :placeholder="keepSecretHint ? 'Leave blank to keep current secret' : ''"
                                        class="pl-9"
                                    />
                                </div>
                                <p class="text-muted-foreground text-xs">Used to HMAC-SHA256 the request body; verify on your end.</p>
                                <InputError :message="form.errors.auth_value" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t pt-5">
                            <Button
                                v-if="isEdit"
                                type="button"
                                variant="ghost"
                                class="text-destructive hover:text-destructive mr-auto"
                                @click="askDelete"
                            >
                                <Trash2 class="mr-2 size-4" />
                                Delete
                            </Button>
                            <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">
                                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                                <Save v-else class="mr-2 size-4" />
                                {{ isEdit ? 'Save changes' : 'Add function' }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <TabsContent v-if="isEdit" value="agents" class="pt-6">
                    <p class="text-muted-foreground mb-3 text-sm">Toggle which of your agents can use this function.</p>

                    <div v-if="agents.loading.value" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="size-4 animate-spin" />
                        Loading agents
                    </div>

                    <div v-else-if="agents.all.value.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                        You have no agents yet.
                    </div>

                    <ul v-else class="divide-border divide-y">
                        <li
                            v-for="agent in agents.all.value"
                            :key="agent.id"
                            class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="truncate text-sm font-medium">{{ agent.name }}</span>
                            <Switch
                                :model-value="agent.attached"
                                :disabled="agents.busyId.value === agent.id"
                                @update:model-value="() => onToggleAttachment(agent.id)"
                            />
                        </li>
                    </ul>
                </TabsContent>
            </Tabs>

            <div class="h-6"></div>
        </SheetContent>
    </Sheet>

    <DeleteConfirmationDialog
        :open="deleteOpen"
        :title="`Delete ${func?.name ?? 'function'}?`"
        description="It will be detached from any agents using it. This action cannot be undone."
        confirm-text="Delete"
        cancel-text="Keep"
        @cancel="deleteOpen = false"
        @confirm="confirmDelete"
    />
</template>
