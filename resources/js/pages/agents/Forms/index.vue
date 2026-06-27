<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { FormKitSchemaDefinition } from '@formkit/core';
import { FormKit, FormKitSchema } from '@formkit/vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { CircleHelp, FileText, Plus, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Field {
    key: string;
    label: string;
    type: string;
    required: boolean;
    placeholder?: string;
    options?: { value: string; label: string }[];
    leadField?: string;
}

interface FormDef {
    external_id?: string | null;
    slug?: string | null;
    name: string;
    subtitle: string | null;
    form_type: string;
    schema: Field[];
    lead_field_map: Record<string, string> | null;
    success_message: string | null;
    agent_instructions: string | null;
    is_active: boolean;
    submissions_count: number;
}

const props = defineProps<{
    agent: { external_id: string; name: string };
    forms: FormDef[];
    leadFields: { key: string; label: string }[];
}>();

const { t } = useI18n();

const FIELD_TYPES = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'multicheckbox', 'date'];
const FORM_TYPES = ['lead', 'qualification', 'booking', 'generic'];
const OPTION_TYPES = ['select', 'radio', 'multicheckbox'];

const editing = ref<FormDef | null>(null);
const saving = ref(false);
const rewriting = ref(false);

// Ask the LLM to turn the rough "when to show" note into a precise, intent-based
// trigger. The suggestion only fills the textarea — it's saved with the form.
async function rewriteDescription(): Promise<void> {
    if (!editing.value || rewriting.value) return;
    rewriting.value = true;
    try {
        const { data } = await axios.post(route('agents.forms.rewrite-description', props.agent.external_id), {
            agent_instructions: editing.value.agent_instructions,
            name: editing.value.name,
            form_type: editing.value.form_type,
        });
        editing.value.agent_instructions = data.agent_instructions;
        toast.success(t('agents.forms.rewrite_done'));
    } catch {
        toast.error(t('agents.forms.rewrite_failed'));
    } finally {
        rewriting.value = false;
    }
}

function blankForm(): FormDef {
    return {
        name: '',
        subtitle: null,
        form_type: 'lead',
        schema: [{ key: 'name', label: 'Name', type: 'text', required: true, leadField: 'name' }],
        lead_field_map: null,
        success_message: null,
        agent_instructions: null,
        is_active: true,
        submissions_count: 0,
    };
}

function startNew(): void {
    editing.value = blankForm();
}

function edit(form: FormDef): void {
    // Re-hydrate per-field leadField from the saved map for the editor.
    const map = form.lead_field_map ?? {};
    editing.value = {
        ...form,
        schema: form.schema.map((f) => ({ ...f, leadField: map[f.key] })),
    };
}

function cancel(): void {
    editing.value = null;
}

const isLeadBound = computed(() => editing.value !== null && ['lead', 'qualification'].includes(editing.value.form_type));

// Static t() calls so the missing-key validator can see them (no dynamic key).
function formTypeLabel(ft: string): string {
    switch (ft) {
        case 'lead':
            return t('agents.forms.type_lead');
        case 'qualification':
            return t('agents.forms.type_qualification');
        case 'booking':
            return t('agents.forms.type_booking');
        default:
            return t('agents.forms.type_generic');
    }
}

function slugKey(label: string, taken: string[]): string {
    const base =
        (label || 'field')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || 'field';
    let key = base;
    let i = 1;
    while (taken.includes(key)) key = `${base}_${++i}`;
    return key;
}

function addField(): void {
    if (!editing.value) return;
    const taken = editing.value.schema.map((f) => f.key);
    editing.value.schema.push({ key: slugKey('field', taken), label: '', type: 'text', required: false });
}

function removeField(i: number): void {
    editing.value?.schema.splice(i, 1);
}

function onLabelBlur(field: Field): void {
    if (!editing.value) return;
    if (!field.label) return;
    // Keep an empty/auto key in sync with the label; leave explicit keys alone.
    if (!field.key || /^field(_\d+)?$/.test(field.key)) {
        field.key = slugKey(
            field.label,
            editing.value.schema.filter((f) => f !== field).map((f) => f.key),
        );
    }
}

function parseOptions(text: string): { value: string; label: string }[] {
    return text
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
        .map((l) => {
            const [value, label] = l.split('|').map((s) => s.trim());
            return { value: value, label: label || value };
        });
}

function optionsText(field: Field): string {
    return (field.options ?? []).map((o) => (o.label === o.value ? o.value : `${o.value}|${o.label}`)).join('\n');
}

function setOptions(field: Field, text: string): void {
    field.options = parseOptions(text);
}

// Live FormKit preview schema (mirrors the server-side FormSchemaBuilder).
const previewSchema = computed<FormKitSchemaDefinition>(() => {
    if (!editing.value) return [];
    const map: Record<string, string> = {
        text: 'text',
        email: 'email',
        tel: 'tel',
        number: 'number',
        textarea: 'textarea',
        select: 'select',
        radio: 'radio',
        checkbox: 'checkbox',
        multicheckbox: 'checkbox',
        date: 'date',
    };
    const nodes = editing.value.schema
        .filter((f) => f.label && f.key)
        .map((f) => {
            const node: Record<string, unknown> = { $formkit: map[f.type] ?? 'text', name: f.key, label: f.label };
            if (f.placeholder) node.placeholder = f.placeholder;
            if (OPTION_TYPES.includes(f.type)) node.options = f.options ?? [];
            if (f.type === 'multicheckbox') node.multiple = true;
            if (f.required) node.validation = 'required';
            return node;
        });
    return nodes as unknown as FormKitSchemaDefinition;
});

function save(): void {
    const form = editing.value;
    if (!form) return;
    if (!form.name.trim()) {
        toast.error(t('agents.forms.name_required'));
        return;
    }

    const leadMap: Record<string, string> = {};
    if (['lead', 'qualification'].includes(form.form_type)) {
        for (const f of form.schema) {
            if (f.leadField) leadMap[f.key] = f.leadField;
        }
    }

    const payload = {
        name: form.name,
        subtitle: form.subtitle,
        form_type: form.form_type,
        success_message: form.success_message,
        agent_instructions: form.agent_instructions,
        is_active: form.is_active,
        schema: form.schema.map((f) => ({
            key: f.key,
            label: f.label,
            type: f.type,
            required: f.required,
            placeholder: f.placeholder ?? null,
            options: OPTION_TYPES.includes(f.type) ? (f.options ?? []) : null,
        })),
        lead_field_map: Object.keys(leadMap).length ? leadMap : null,
    };

    saving.value = true;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            toast.success(t('agents.forms.saved'));
        },
        onError: () => toast.error(t('agents.forms.save_failed')),
        onFinish: () => (saving.value = false),
    };

    if (form.external_id) {
        router.put(route('agents.forms.update', { agent: props.agent.external_id, form: form.external_id }), payload, opts);
    } else {
        router.post(route('agents.forms.store', props.agent.external_id), payload, opts);
    }
}

function destroy(form: FormDef): void {
    if (!form.external_id) return;
    router.delete(route('agents.forms.destroy', { agent: props.agent.external_id, form: form.external_id }), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.forms.deleted')),
    });
}
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.forms.page_title')">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <!-- List -->
            <Card v-if="!editing">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle class="flex items-center gap-2"><FileText class="h-5 w-5" /> {{ t('agents.forms.title') }}</CardTitle>
                            <CardDescription>{{ t('agents.forms.description') }}</CardDescription>
                        </div>
                        <Button @click="startNew"><Plus class="mr-1 h-4 w-4" /> {{ t('agents.forms.new') }}</Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="forms.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                        {{ t('agents.forms.empty') }}
                    </div>
                    <div v-else class="divide-y rounded-lg border">
                        <div v-for="form in forms" :key="form.external_id ?? form.slug ?? form.name" class="flex items-center gap-4 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ form.name }}</p>
                                <p class="text-muted-foreground text-xs">
                                    {{ formTypeLabel(form.form_type) }} · {{ form.schema.length }} {{ t('agents.forms.fields') }}
                                    <span v-if="!form.is_active"> · {{ t('agents.forms.inactive') }}</span>
                                </p>
                            </div>
                            <Link
                                v-if="form.external_id"
                                :href="route('agents.forms.submissions.index', { agent: agent.external_id, form: form.external_id })"
                            >
                                <Button variant="ghost" size="sm">{{ t('agents.forms.submissions') }} ({{ form.submissions_count }})</Button>
                            </Link>
                            <Button variant="outline" size="sm" @click="edit(form)">{{ t('agents.forms.edit') }}</Button>
                            <Button variant="ghost" size="icon" @click="destroy(form)"><Trash2 class="h-4 w-4" /></Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Editor + live preview -->
            <div v-else-if="editing" class="grid gap-6 lg:grid-cols-5">
                <Card class="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>{{ editing.external_id ? t('agents.forms.edit_form') : t('agents.forms.new_form') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">{{ t('agents.forms.form_name') }}</label>
                            <Input v-model="editing.name" :placeholder="t('agents.forms.form_name_placeholder')" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">{{ t('agents.forms.subtitle') }}</label>
                            <Textarea v-model="editing.subtitle" :placeholder="t('agents.forms.subtitle_placeholder')" rows="2" />
                            <p class="text-muted-foreground text-xs">{{ t('agents.forms.subtitle_hint') }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">{{ t('agents.forms.form_type') }}</label>
                            <select v-model="editing.form_type" class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm">
                                <option v-for="ft in FORM_TYPES" :key="ft" :value="ft">{{ formTypeLabel(ft) }}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-sm font-medium">{{ t('agents.forms.description') }}</label>
                                <button
                                    type="button"
                                    class="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="rewriting"
                                    @click="rewriteDescription"
                                >
                                    <Sparkles class="h-3.5 w-3.5" :class="{ 'animate-pulse': rewriting }" />
                                    {{ rewriting ? t('agents.forms.rewriting') : t('agents.forms.rewrite') }}
                                </button>
                            </div>
                            <Textarea v-model="editing.agent_instructions" :placeholder="t('agents.forms.description_placeholder')" rows="2" />
                            <p class="text-muted-foreground text-xs">{{ t('agents.forms.description_hint') }}</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium">{{ t('agents.forms.fields') }}</label>
                                <Button variant="outline" size="sm" @click="addField"
                                    ><Plus class="mr-1 h-4 w-4" /> {{ t('agents.forms.add_field') }}</Button
                                >
                            </div>

                            <div v-for="(field, i) in editing.schema" :key="i" class="space-y-2 rounded-md border p-3">
                                <div class="flex items-center gap-2">
                                    <Input
                                        v-model="field.label"
                                        :placeholder="t('agents.forms.field_label')"
                                        class="flex-1"
                                        @blur="onLabelBlur(field)"
                                    />
                                    <select v-model="field.type" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                        <option v-for="ty in FIELD_TYPES" :key="ty" :value="ty">{{ ty }}</option>
                                    </select>
                                    <Button variant="ghost" size="icon" @click="removeField(i)"><Trash2 class="h-4 w-4" /></Button>
                                </div>

                                <div class="flex flex-wrap items-center gap-4">
                                    <label class="flex items-center gap-2 text-sm">
                                        <Switch :model-value="field.required" @update:model-value="(v) => (field.required = v)" />
                                        {{ t('agents.forms.required') }}
                                    </label>
                                    <label v-if="isLeadBound" class="flex items-center gap-2 text-sm">
                                        <span class="flex items-center gap-1.5">
                                            {{ t('agents.forms.maps_to') }}
                                            <Popover>
                                                <PopoverTrigger as-child>
                                                    <button
                                                        type="button"
                                                        class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                        :aria-label="t('agents.forms.maps_to_aria')"
                                                        @click.prevent
                                                    >
                                                        <CircleHelp class="h-3.5 w-3.5" />
                                                    </button>
                                                </PopoverTrigger>
                                                <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                    <p class="text-foreground font-medium">{{ t('agents.forms.maps_to_help_title') }}</p>
                                                    <p class="text-muted-foreground mt-1.5">{{ t('agents.forms.maps_to_help_1') }}</p>
                                                    <p class="text-muted-foreground mt-1.5">{{ t('agents.forms.maps_to_help_2') }}</p>
                                                </PopoverContent>
                                            </Popover>
                                        </span>
                                        <select v-model="field.leadField" class="border-input bg-background h-8 rounded-md border px-2 text-sm">
                                            <option :value="undefined">—</option>
                                            <option v-for="lf in leadFields" :key="lf.key" :value="lf.key">{{ lf.label }}</option>
                                        </select>
                                    </label>
                                </div>

                                <Textarea
                                    v-if="OPTION_TYPES.includes(field.type)"
                                    :model-value="optionsText(field)"
                                    :placeholder="t('agents.forms.options_placeholder')"
                                    rows="3"
                                    @update:model-value="(v) => setOptions(field, String(v))"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">{{ t('agents.forms.success_message') }}</label>
                            <Textarea v-model="editing.success_message" :placeholder="t('agents.forms.success_placeholder')" rows="2" />
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <Switch :model-value="editing.is_active" @update:model-value="(v) => editing && (editing.is_active = v)" />
                            {{ t('agents.forms.active') }}
                        </label>

                        <div class="flex gap-2 pt-2">
                            <Button :disabled="saving" @click="save">{{ t('agents.forms.save') }}</Button>
                            <Button variant="outline" :disabled="saving" @click="cancel">{{ t('agents.forms.cancel') }}</Button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader
                        ><CardTitle>{{ t('agents.forms.preview') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <div v-if="editing.name || editing.subtitle" class="mb-4 border-b pb-3">
                            <p v-if="editing.name" class="text-base font-semibold">{{ editing.name }}</p>
                            <p v-if="editing.subtitle" class="text-muted-foreground mt-1 text-sm">{{ editing.subtitle }}</p>
                        </div>
                        <FormKit type="form" :actions="false">
                            <FormKitSchema :schema="previewSchema" />
                        </FormKit>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
