<script setup lang="ts">
/**
 * The Info editor of the Setup page: chat title, welcome message, composer
 * placeholder and the notice under the composer. The "Default" tab edits the base strings; each flag tab edits
 * that language's manual translation (placeholder = the AI translation).
 */
import type { LocaleMap } from '@/dashboard/agents/api/agentSetup';
import AppearanceTextFields from '@/dashboard/agents/components/AppearanceTextFields.vue';
import TranslationLanguageTabs from '@/dashboard/agents/components/setup/TranslationLanguageTabs.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type FieldKey = 'chat_window_title' | 'welcome_message' | 'composer_placeholder' | 'notice';

const props = withDefaults(
    defineProps<{
        // Base (source-language) strings — edited on the "Default" tab.
        welcomeMessage: string;
        chatWindowTitle: string;
        composerPlaceholder: string;
        notice: string;
        // AI translations (read-only here) shown as the placeholder for each
        // language tab, so an empty field falls back to the automatic value.
        translations: LocaleMap;
        // Manual per-language edits — what the language tabs write.
        overrides: LocaleMap;
        // Allowed language codes (config('chatvia.languages')); restricts the
        // flag tabs to the supported set.
        languages?: Record<string, string>;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { disabled: false, errors: () => ({}), languages: () => ({}), translations: () => ({}), overrides: () => ({}) },
);

const emit = defineEmits<{
    'update:welcomeMessage': [value: string];
    'update:chatWindowTitle': [value: string];
    'update:composerPlaceholder': [value: string];
    'update:notice': [value: string];
    'update:overrides': [value: LocaleMap];
}>();

const { t } = useI18n();

// 'default' = base strings; otherwise a locale code.
const selected = ref<string>('default');

const isDefault = computed(() => selected.value === 'default');

const BASE: Record<FieldKey, () => string> = {
    welcome_message: () => props.welcomeMessage,
    chat_window_title: () => props.chatWindowTitle,
    composer_placeholder: () => props.composerPlaceholder,
    notice: () => props.notice,
};

const baseFor = (field: FieldKey): string => BASE[field]();

const fieldValue = (field: FieldKey): string => {
    if (isDefault.value) return baseFor(field);
    return props.overrides?.[field]?.[selected.value] ?? '';
};

// Placeholder for a language tab = the automatic translation, else the base.
const placeholderFor = (field: FieldKey): string => (isDefault.value ? '' : props.translations?.[field]?.[selected.value] || baseFor(field));

const placeholders = computed(() =>
    isDefault.value
        ? {}
        : {
              chat_window_title: placeholderFor('chat_window_title'),
              welcome_message: placeholderFor('welcome_message'),
              composer_placeholder: placeholderFor('composer_placeholder'),
              notice: placeholderFor('notice'),
          },
);

const update = (field: FieldKey, value: string): void => {
    if (isDefault.value) {
        if (field === 'welcome_message') emit('update:welcomeMessage', value);
        else if (field === 'chat_window_title') emit('update:chatWindowTitle', value);
        else if (field === 'notice') emit('update:notice', value);
        else emit('update:composerPlaceholder', value);
        return;
    }

    const next: LocaleMap = JSON.parse(JSON.stringify(props.overrides ?? {}));
    next[field] ??= {};
    if (value.trim() === '') delete next[field][selected.value];
    else next[field][selected.value] = value;
    emit('update:overrides', next);
};

// A language tab shows a dot when it has at least one manual override.
const hasOverride = (code: string): boolean =>
    (['chat_window_title', 'welcome_message', 'composer_placeholder', 'notice'] as FieldKey[]).some(
        (field) => (props.overrides?.[field]?.[code] ?? '').trim() !== '',
    );
</script>

<template>
    <div class="space-y-5">
        <!-- Language tabs (flags). The first tab edits the base text; each flag
             tab edits that language's override. -->
        <TranslationLanguageTabs v-model="selected" :languages="languages" :has-override="hasOverride" />

        <AppearanceTextFields
            :welcome-message="fieldValue('welcome_message')"
            :chat-window-title="fieldValue('chat_window_title')"
            :composer-placeholder="fieldValue('composer_placeholder')"
            :notice="fieldValue('notice')"
            :placeholders="placeholders"
            :errors="isDefault ? errors : {}"
            :disabled="disabled"
            @update:welcome-message="update('welcome_message', $event)"
            @update:chat-window-title="update('chat_window_title', $event)"
            @update:composer-placeholder="update('composer_placeholder', $event)"
            @update:notice="update('notice', $event)"
        />

        <p class="text-muted-foreground text-xs">
            {{
                isDefault
                    ? t('agents.setup.sections.appearance_translations_default_hint')
                    : t('agents.setup.sections.appearance_translations_lang_hint')
            }}
        </p>
    </div>
</template>
