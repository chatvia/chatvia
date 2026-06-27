<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MessageCircle, Pencil, Type } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

withDefaults(
    defineProps<{
        welcomeMessage: string;
        chatWindowTitle: string;
        composerPlaceholder: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
        // Optional per-field input placeholders. When set (e.g. the automatic
        // translation for a language tab), they replace the default static hints
        // so an empty field clearly means "fall back to this value".
        placeholders?: { chat_window_title?: string; welcome_message?: string; composer_placeholder?: string };
    }>(),
    { disabled: false, errors: () => ({}), placeholders: () => ({}) },
);

defineEmits<{
    'update:welcomeMessage': [value: string];
    'update:chatWindowTitle': [value: string];
    'update:composerPlaceholder': [value: string];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <Label for="chat_window_title">{{ t('agents.setup.sections.appearance_chat_title') }}</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                    <Type class="h-4 w-4" />
                </div>
                <Input
                    id="chat_window_title"
                    :model-value="chatWindowTitle"
                    :placeholder="placeholders?.chat_window_title || t('agents.setup.sections.appearance_chat_title_placeholder')"
                    :disabled="disabled"
                    class="ps-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="$emit('update:chatWindowTitle', String($event))"
                />
            </div>
            <p v-if="errors?.chat_window_title" class="text-destructive text-sm">{{ errors.chat_window_title }}</p>
        </div>

        <div class="space-y-2">
            <Label for="welcome_message">{{ t('agents.setup.sections.appearance_welcome') }}</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                    <MessageCircle class="h-4 w-4" />
                </div>
                <Input
                    id="welcome_message"
                    :model-value="welcomeMessage"
                    :placeholder="placeholders?.welcome_message || t('agents.setup.sections.appearance_welcome_placeholder')"
                    :disabled="disabled"
                    class="ps-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="$emit('update:welcomeMessage', String($event))"
                />
            </div>
            <p v-if="errors?.welcome_message" class="text-destructive text-sm">{{ errors.welcome_message }}</p>
        </div>

        <div class="space-y-2">
            <Label for="composer_placeholder">{{ t('agents.setup.sections.appearance_composer') }}</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                    <Pencil class="h-4 w-4" />
                </div>
                <Input
                    id="composer_placeholder"
                    :model-value="composerPlaceholder"
                    :placeholder="placeholders?.composer_placeholder || t('agents.setup.sections.appearance_composer_placeholder')"
                    :disabled="disabled"
                    class="ps-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="$emit('update:composerPlaceholder', String($event))"
                />
            </div>
            <p v-if="errors?.composer_placeholder" class="text-destructive text-sm">{{ errors.composer_placeholder }}</p>
        </div>
    </div>
</template>
