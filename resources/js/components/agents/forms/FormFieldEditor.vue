<script setup lang="ts">
/**
 * One field row in the form editor: label, input type, required switch, the
 * optional "Maps to" contact field (lead-shaped forms only) and, for choice
 * types, the option list. Edits the field object in place.
 */
import { FIELD_TYPES, type FormField, type LeadField, OPTION_TYPES, optionsText, parseOptions } from '@/components/agents/forms/formDefinition';
import HelpPopover from '@/components/common/HelpPopover.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    leadBound: boolean;
    leadFields: LeadField[];
}>();

const field = defineModel<FormField>('field', { required: true });

const emit = defineEmits<{
    remove: [];
    labelBlur: [];
}>();

const { t } = useI18n();

function setOptions(text: string): void {
    field.value.options = parseOptions(text);
}
</script>

<template>
    <div class="space-y-2 rounded-md border p-3">
        <div class="flex items-center gap-2">
            <Input v-model="field.label" :placeholder="t('agents.forms.field_label')" class="flex-1" @blur="emit('labelBlur')" />
            <select v-model="field.type" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                <option v-for="type in FIELD_TYPES" :key="type" :value="type">{{ type }}</option>
            </select>
            <Button variant="ghost" size="icon" :aria-label="t('common.remove')" @click="emit('remove')"><Trash2 class="h-4 w-4" /></Button>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm">
                <Switch :model-value="field.required" @update:model-value="(value) => (field.required = value)" />
                {{ t('agents.forms.required') }}
            </label>
            <label v-if="leadBound" class="flex items-center gap-2 text-sm">
                <span class="flex items-center gap-1.5">
                    {{ t('agents.forms.maps_to') }}
                    <HelpPopover
                        :label="t('agents.forms.maps_to_aria')"
                        :title="t('agents.forms.maps_to_help_title')"
                        :paragraphs="[t('agents.forms.maps_to_help_1'), t('agents.forms.maps_to_help_2')]"
                    />
                </span>
                <select v-model="field.leadField" class="border-input bg-background h-8 rounded-md border px-2 text-sm">
                    <option :value="undefined">—</option>
                    <option v-for="leadField in leadFields" :key="leadField.key" :value="leadField.key">{{ leadField.label }}</option>
                </select>
            </label>
        </div>

        <Textarea
            v-if="OPTION_TYPES.includes(field.type)"
            :model-value="optionsText(field)"
            :placeholder="t('agents.forms.options_placeholder')"
            rows="3"
            @update:model-value="(value) => setOptions(String(value))"
        />
    </div>
</template>
