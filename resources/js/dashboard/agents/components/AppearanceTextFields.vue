<script setup lang="ts">
/**
 * The appearance text inputs (chat title, welcome message, composer
 * placeholder, notice under the composer), each with its icon and validation
 * error. Used by AppearanceTextEditor for the base text and for each
 * language tab.
 */
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Info, MessageCircle, Pencil, Type } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

type FieldKey = 'chat_window_title' | 'welcome_message' | 'composer_placeholder' | 'notice';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        welcomeMessage: string;
        chatWindowTitle: string;
        composerPlaceholder: string;
        notice: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
        // Optional per-field input placeholders. When set (e.g. the automatic
        // translation for a language tab), they replace the default static hints
        // so an empty field clearly means "fall back to this value".
        placeholders?: { chat_window_title?: string; welcome_message?: string; composer_placeholder?: string; notice?: string };
    }>(),
    { disabled: false, errors: () => ({}), placeholders: () => ({}) },
);

const emit = defineEmits<{
    'update:welcomeMessage': [value: string];
    'update:chatWindowTitle': [value: string];
    'update:composerPlaceholder': [value: string];
    'update:notice': [value: string];
}>();

interface Field {
    key: FieldKey;
    label: string;
    value: string;
    placeholder: string;
    icon: Component;
    update: (value: string) => void;
}

const fields = computed<Field[]>(() => [
    {
        key: 'chat_window_title',
        label: t('agents.setup.sections.appearance_chat_title'),
        value: props.chatWindowTitle,
        placeholder: props.placeholders?.chat_window_title || t('agents.setup.sections.appearance_chat_title_placeholder'),
        icon: Type,
        update: (value) => emit('update:chatWindowTitle', value),
    },
    {
        key: 'welcome_message',
        label: t('agents.setup.sections.appearance_welcome'),
        value: props.welcomeMessage,
        placeholder: props.placeholders?.welcome_message || t('agents.setup.sections.appearance_welcome_placeholder'),
        icon: MessageCircle,
        update: (value) => emit('update:welcomeMessage', value),
    },
    {
        key: 'composer_placeholder',
        label: t('agents.setup.sections.appearance_composer'),
        value: props.composerPlaceholder,
        placeholder: props.placeholders?.composer_placeholder || t('agents.setup.sections.appearance_composer_placeholder'),
        icon: Pencil,
        update: (value) => emit('update:composerPlaceholder', value),
    },
    {
        key: 'notice',
        label: t('agents.setup.sections.appearance_notice'),
        value: props.notice,
        placeholder: props.placeholders?.notice || t('agents.setup.sections.appearance_notice_placeholder'),
        icon: Info,
        update: (value) => emit('update:notice', value),
    },
]);
</script>

<template>
    <div class="space-y-6">
        <div v-for="field in fields" :key="field.key" class="space-y-2">
            <Label :for="field.key">{{ field.label }}</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                    <component :is="field.icon" class="h-4 w-4" />
                </div>
                <Input
                    :id="field.key"
                    :model-value="field.value"
                    :placeholder="field.placeholder"
                    :disabled="disabled"
                    class="ps-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="field.update(String($event))"
                />
            </div>
            <p v-if="field.key === 'notice'" class="text-muted-foreground text-xs">{{ t('agents.setup.sections.appearance_notice_help') }}</p>
            <p v-if="errors?.[field.key]" class="text-destructive text-sm">{{ errors[field.key] }}</p>
        </div>
    </div>
</template>
