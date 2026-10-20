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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useFieldTypeLabel } from '@/composables/agents/forms/useFieldTypeLabel';
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
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
const { fieldTypeLabel } = useFieldTypeLabel();

// A select item can't hold an empty value, so "not mapped" is this sentinel.
const UNMAPPED = '__none';

const leadField = computed<string>({
    get: () => field.value.leadField ?? UNMAPPED,
    set: (value) => {
        field.value.leadField = value === UNMAPPED ? undefined : value;
    },
});

function setOptions(text: string): void {
    field.value.options = parseOptions(text);
}
</script>

<template>
    <div class="space-y-2 rounded-md border p-3">
        <div class="flex items-center gap-2">
            <Input v-model="field.label" :placeholder="t('agents.forms.field_label')" class="flex-1" @blur="emit('labelBlur')" />
            <Select v-model="field.type">
                <SelectTrigger class="w-40">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="type in FIELD_TYPES" :key="type" :value="type">{{ fieldTypeLabel(type) }}</SelectItem>
                </SelectContent>
            </Select>
            <Button variant="ghost" size="icon" :aria-label="t('common.remove')" @click="emit('remove')"><Trash2 class="h-4 w-4" /></Button>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm">
                <Switch :model-value="field.required" @update:model-value="(value) => (field.required = value)" />
                {{ t('agents.forms.required') }}
            </label>
            <div v-if="leadBound" class="flex items-center gap-2 text-sm">
                <span class="flex items-center gap-1.5">
                    {{ t('agents.forms.maps_to') }}
                    <HelpPopover
                        :label="t('agents.forms.maps_to_aria')"
                        :title="t('agents.forms.maps_to_help_title')"
                        :paragraphs="[t('agents.forms.maps_to_help_1'), t('agents.forms.maps_to_help_2')]"
                    />
                </span>
                <Select v-model="leadField">
                    <SelectTrigger size="sm" class="min-w-32" :aria-label="t('agents.forms.maps_to')">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="UNMAPPED">—</SelectItem>
                        <SelectItem v-for="option in leadFields" :key="option.key" :value="option.key">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
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
