<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import JsonCodeEditor from '@/components/ui/JsonCodeEditor.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { AgentFunction, AgentFunctionDraft, AuthType } from '@/types/agent-function';
import { DEFAULT_PARAMETERS_SCHEMA, EMPTY_FUNCTION_DRAFT } from '@/types/agent-function';
import axios from 'axios';
import { ExternalLink, LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
    agentId: string;
    existing: AgentFunction | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [fn: AgentFunction];
}>();

const draft = ref<AgentFunctionDraft>({ ...EMPTY_FUNCTION_DRAFT });
const schemaText = ref<string>(JSON.stringify(DEFAULT_PARAMETERS_SCHEMA, null, 2));
const schemaError = ref<string | null>(null);
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

const isEdit = computed(() => props.existing !== null);

const TEMPLATES: Array<{ label: string; schema: object }> = [
    {
        label: 'Empty',
        schema: { type: 'object', properties: {}, required: [] },
    },
    {
        label: 'get_weather(city)',
        schema: {
            type: 'object',
            properties: {
                city: { type: 'string', description: 'City name, e.g. "Oslo"' },
            },
            required: ['city'],
        },
    },
    {
        label: 'search(query, limit)',
        schema: {
            type: 'object',
            properties: {
                query: { type: 'string', description: 'What to search for' },
                limit: { type: 'integer', description: 'Max results', default: 10 },
            },
            required: ['query'],
        },
    },
];

watch(
    () => [props.open, props.existing],
    () => {
        if (!props.open) return;
        const fn = props.existing;
        if (fn) {
            draft.value = {
                name: fn.name,
                description: fn.description,
                parameters_schema: fn.parameters_schema,
                webhook_url: fn.webhook_url,
                webhook_method: fn.webhook_method,
                webhook_headers: [...fn.webhook_headers],
                auth_type: fn.auth_type,
                auth_value: null, // never round-trip a secret
                timeout_ms: fn.timeout_ms,
                max_response_bytes: fn.max_response_bytes,
                enabled: fn.enabled,
            };
            schemaText.value = JSON.stringify(fn.parameters_schema, null, 2);
        } else {
            draft.value = { ...EMPTY_FUNCTION_DRAFT };
            schemaText.value = JSON.stringify(DEFAULT_PARAMETERS_SCHEMA, null, 2);
        }
        schemaError.value = null;
        errors.value = {};
    },
    { immediate: true },
);

watch(schemaText, (text) => {
    try {
        const parsed = JSON.parse(text);
        if (typeof parsed !== 'object' || parsed === null || parsed.type !== 'object') {
            schemaError.value = 'Schema must be a JSON object with type "object".';
            return;
        }
        draft.value.parameters_schema = parsed;
        schemaError.value = null;
    } catch (e) {
        schemaError.value = (e as Error).message;
    }
});

function applyTemplate(template: object): void {
    schemaText.value = JSON.stringify(template, null, 2);
}

function addHeader(): void {
    draft.value.webhook_headers.push({ key: '', value: '' });
}

function removeHeader(i: number): void {
    draft.value.webhook_headers.splice(i, 1);
}

async function save(): Promise<void> {
    if (schemaError.value) {
        toast.error('Fix the parameters schema before saving');
        return;
    }

    saving.value = true;
    errors.value = {};

    const payload = {
        ...draft.value,
        webhook_headers: draft.value.webhook_headers.filter((h) => h.key && h.value),
    };

    try {
        let response;
        if (props.existing) {
            response = await axios.patch(
                route('agents.sources.functions.update', {
                    agent: props.agentId,
                    function: props.existing.external_id,
                }),
                payload,
            );
        } else {
            response = await axios.post(
                route('agents.sources.functions.store', props.agentId),
                payload,
            );
        }
        toast.success(props.existing ? `Updated ${response.data.data.name}` : `Created ${response.data.data.name}`);
        emit('saved', response.data.data);
    } catch (err) {
        const status = (err as { response?: { status: number; data: { errors?: Record<string, string[]> } } }).response?.status;
        const respErrors = (err as { response?: { data: { errors?: Record<string, string[]> } } }).response?.data?.errors;
        if (status === 422 && respErrors) {
            errors.value = respErrors;
        } else {
            console.error(err);
            toast.error('Failed to save function');
        }
    } finally {
        saving.value = false;
    }
}

function fieldError(field: string): string | null {
    return errors.value[field]?.[0] ?? null;
}

function authTypeChanged(type: AuthType): void {
    draft.value.auth_type = type;
    if (type === 'none' || type === 'hmac') {
        draft.value.auth_value = null;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent class="flex w-full flex-col overflow-hidden p-0 sm:max-w-2xl">
            <SheetHeader class="border-b px-6 pt-6 pb-4 text-left">
                <SheetTitle>{{ isEdit ? 'Edit function' : 'New function' }}</SheetTitle>
                <SheetDescription>
                    Functions let the chat call your API as a tool. Give the chat a description of when
                    to call it, the JSON-Schema of its arguments, and a webhook URL.
                    <a
                        href="https://platform.openai.com/docs/guides/function-calling"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary inline-flex items-center gap-1 underline-offset-2 hover:underline"
                    >
                        Learn more
                        <ExternalLink class="h-3 w-3" />
                    </a>
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="fn-name">Function name</Label>
                    <Input
                        id="fn-name"
                        v-model="draft.name"
                        placeholder="get_weather"
                        :disabled="isEdit"
                        autocomplete="off"
                    />
                    <p class="text-muted-foreground text-xs">
                        Letters, numbers, and underscores. Must start with a letter. The chat sees this name.
                    </p>
                    <p v-if="fieldError('name')" class="text-destructive text-xs">{{ fieldError('name') }}</p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="fn-desc">Description</Label>
                    <Textarea
                        id="fn-desc"
                        v-model="draft.description"
                        rows="3"
                        placeholder="Returns the current weather for the given city."
                    />
                    <p class="text-muted-foreground text-xs">
                        Be specific — the chat uses this to decide when to call your function.
                    </p>
                    <p v-if="fieldError('description')" class="text-destructive text-xs">{{ fieldError('description') }}</p>
                </div>

                <!-- Parameters schema -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Parameters schema (JSON Schema)</Label>
                        <div class="flex gap-1">
                            <Button
                                v-for="t in TEMPLATES"
                                :key="t.label"
                                size="sm"
                                variant="ghost"
                                class="text-xs"
                                @click="applyTemplate(t.schema)"
                            >
                                {{ t.label }}
                            </Button>
                        </div>
                    </div>
                    <JsonCodeEditor v-model="schemaText" :rows="12" aria-label="Parameters schema" />
                    <p v-if="schemaError" class="text-destructive text-xs">{{ schemaError }}</p>
                    <p v-else-if="fieldError('parameters_schema')" class="text-destructive text-xs">
                        {{ fieldError('parameters_schema') }}
                    </p>
                </div>

                <!-- Webhook -->
                <div class="space-y-2">
                    <Label>Webhook URL</Label>
                    <div class="flex gap-2">
                        <Select
                            :model-value="draft.webhook_method"
                            @update:model-value="(v) => (draft.webhook_method = v as 'POST' | 'GET')"
                        >
                            <SelectTrigger class="w-24">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="POST">POST</SelectItem>
                                <SelectItem value="GET">GET</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input v-model="draft.webhook_url" placeholder="https://api.example.com/tools/weather" class="flex-1" />
                    </div>
                    <p v-if="fieldError('webhook_url')" class="text-destructive text-xs">{{ fieldError('webhook_url') }}</p>
                </div>

                <!-- Auth -->
                <div class="space-y-2">
                    <Label>Authentication</Label>
                    <Select :model-value="draft.auth_type" @update:model-value="(v) => authTypeChanged(v as AuthType)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">None</SelectItem>
                            <SelectItem value="bearer">Bearer token</SelectItem>
                            <SelectItem value="header">Custom header value</SelectItem>
                            <SelectItem value="hmac">HMAC signature only</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        v-if="draft.auth_type === 'bearer' || draft.auth_type === 'header'"
                        v-model="draft.auth_value"
                        type="password"
                        :placeholder="draft.auth_type === 'bearer' ? 'Bearer token value' : 'Header value'"
                        autocomplete="off"
                    />
                    <p v-if="fieldError('auth_value')" class="text-destructive text-xs">{{ fieldError('auth_value') }}</p>
                    <p v-if="draft.auth_type === 'hmac'" class="text-muted-foreground text-xs">
                        ChatVia signs every outgoing request with HMAC-SHA256 over the body using the
                        function's secret. You verify it with the secret shown after saving.
                    </p>
                </div>

                <!-- Headers -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Custom headers</Label>
                        <Button size="sm" variant="ghost" @click="addHeader">
                            <Plus class="mr-1 h-3.5 w-3.5" /> Add
                        </Button>
                    </div>
                    <div v-if="draft.webhook_headers.length === 0" class="text-muted-foreground text-xs">
                        No custom headers.
                    </div>
                    <div
                        v-for="(h, i) in draft.webhook_headers"
                        :key="i"
                        class="flex gap-2"
                    >
                        <Input v-model="h.key" placeholder="X-Header-Name" class="w-1/3" />
                        <Input v-model="h.value" placeholder="value" class="flex-1" />
                        <Button variant="ghost" size="icon" @click="removeHeader(i)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Limits -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="fn-timeout">Timeout (ms)</Label>
                        <Input id="fn-timeout" v-model.number="draft.timeout_ms" type="number" min="500" max="30000" />
                    </div>
                    <div class="space-y-2">
                        <Label for="fn-bytes">Max response (bytes)</Label>
                        <Input id="fn-bytes" v-model.number="draft.max_response_bytes" type="number" min="1024" max="524288" />
                    </div>
                </div>

                <!-- Enabled -->
                <div class="flex items-center justify-between">
                    <div>
                        <Label class="block">Enabled</Label>
                        <p class="text-muted-foreground text-xs">When off, the chat won't see this function.</p>
                    </div>
                    <Switch v-model="draft.enabled" />
                </div>
            </div>

            <SheetFooter class="border-t px-6 pt-4 pb-6 gap-2">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button :disabled="saving" @click="save">
                    <LoaderCircle v-if="saving" class="mr-1 h-4 w-4 animate-spin" />
                    {{ isEdit ? 'Save changes' : 'Create function' }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
