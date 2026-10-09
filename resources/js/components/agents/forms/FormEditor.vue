<script setup lang="ts">
/**
 * The editor card of the agent's Forms page: name, description, type, the
 * "when to show it" note (with an AI rewrite), the field list, success message
 * and the active switch. Edits the draft in place; saving, cancelling and the
 * rewrite request are left to the page.
 */
import FormFieldEditor from '@/components/agents/forms/FormFieldEditor.vue';
import { FORM_TYPES, type FormDefinition, type FormField, isLeadBoundType, type LeadField, slugKey } from '@/components/agents/forms/formDefinition';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useFormTypeLabel } from '@/composables/agents/forms/useFormTypeLabel';
import { Plus, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    leadFields: LeadField[];
    saving: boolean;
    rewriting: boolean;
}>();

const form = defineModel<FormDefinition>('form', { required: true });

const emit = defineEmits<{
    save: [];
    cancel: [];
    rewrite: [];
}>();

const { t } = useI18n();
const { formTypeLabel } = useFormTypeLabel();

const isLeadBound = computed(() => isLeadBoundType(form.value.form_type));

function addField(): void {
    const taken = form.value.schema.map((field) => field.key);
    form.value.schema.push({ key: slugKey('field', taken), label: '', type: 'text', required: false });
}

function removeField(index: number): void {
    form.value.schema.splice(index, 1);
}

// Keep an empty or auto-generated key in sync with the label; leave keys the
// owner chose alone.
function onLabelBlur(field: FormField): void {
    if (!field.label) return;
    if (!field.key || /^field(_\d+)?$/.test(field.key)) {
        field.key = slugKey(
            field.label,
            form.value.schema.filter((other) => other !== field).map((other) => other.key),
        );
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ form.external_id ? t('agents.forms.edit_form') : t('agents.forms.new_form') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-5">
            <div class="space-y-1.5">
                <label class="text-sm font-medium">{{ t('agents.forms.form_name') }}</label>
                <Input v-model="form.name" :placeholder="t('agents.forms.form_name_placeholder')" />
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium">{{ t('agents.forms.subtitle') }}</label>
                <Textarea v-model="form.subtitle" :placeholder="t('agents.forms.subtitle_placeholder')" rows="2" />
                <p class="text-muted-foreground text-xs">{{ t('agents.forms.subtitle_hint') }}</p>
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium">{{ t('agents.forms.form_type') }}</label>
                <select v-model="form.form_type" class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm">
                    <option v-for="formType in FORM_TYPES" :key="formType" :value="formType">{{ formTypeLabel(formType) }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <label class="text-sm font-medium">{{ t('agents.forms.description') }}</label>
                    <button
                        type="button"
                        class="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="rewriting"
                        @click="emit('rewrite')"
                    >
                        <Sparkles class="h-3.5 w-3.5" :class="{ 'animate-pulse': rewriting }" />
                        {{ rewriting ? t('agents.forms.rewriting') : t('agents.forms.rewrite') }}
                    </button>
                </div>
                <Textarea v-model="form.agent_instructions" :placeholder="t('agents.forms.description_placeholder')" rows="2" />
                <p class="text-muted-foreground text-xs">{{ t('agents.forms.description_hint') }}</p>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">{{ t('agents.forms.fields') }}</label>
                    <Button variant="outline" size="sm" @click="addField"><Plus class="me-1 h-4 w-4" /> {{ t('agents.forms.add_field') }}</Button>
                </div>

                <FormFieldEditor
                    v-for="(field, index) in form.schema"
                    :key="index"
                    v-model:field="form.schema[index]"
                    :lead-bound="isLeadBound"
                    :lead-fields="leadFields"
                    @remove="removeField(index)"
                    @label-blur="onLabelBlur(field)"
                />
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium">{{ t('agents.forms.success_message') }}</label>
                <Textarea v-model="form.success_message" :placeholder="t('agents.forms.success_placeholder')" rows="2" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <Switch :model-value="form.is_active" @update:model-value="(value) => (form.is_active = value)" />
                {{ t('agents.forms.active') }}
            </label>

            <div class="flex gap-2 pt-2">
                <Button :disabled="saving" @click="emit('save')">{{ t('agents.forms.save') }}</Button>
                <Button variant="outline" :disabled="saving" @click="emit('cancel')">{{ t('agents.forms.cancel') }}</Button>
            </div>
        </CardContent>
    </Card>
</template>
