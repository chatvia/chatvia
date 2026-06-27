<script setup lang="ts">
import AppearanceTextFields from '@/components/agents/AppearanceTextFields.vue';
import { LOCALES } from '@/i18n';
import { Globe } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type LocaleMap = Record<string, Record<string, string>>;
type FieldKey = 'chat_window_title' | 'welcome_message' | 'composer_placeholder';

const props = withDefaults(
    defineProps<{
        // Base (source-language) strings — edited on the "Default" tab.
        welcomeMessage: string;
        chatWindowTitle: string;
        composerPlaceholder: string;
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
    'update:overrides': [value: LocaleMap];
}>();

const { t } = useI18n();

// 'default' = base strings; otherwise a locale code.
const selected = ref<string>('default');

// Flag tabs limited to the supported languages (falls back to all when no
// allow-list is supplied).
const tabs = computed(() => {
    const allowed = Object.keys(props.languages).filter((code) => code !== 'auto');
    return allowed.length ? LOCALES.filter((l) => allowed.includes(l.code)) : LOCALES;
});

const isDefault = computed(() => selected.value === 'default');

const baseFor = (field: FieldKey): string =>
    field === 'welcome_message' ? props.welcomeMessage : field === 'chat_window_title' ? props.chatWindowTitle : props.composerPlaceholder;

const fieldValue = (field: FieldKey): string => {
    if (isDefault.value) return baseFor(field);
    return props.overrides?.[field]?.[selected.value] ?? '';
};

// Placeholder for a language tab = the automatic translation, else the base.
const placeholderFor = (field: FieldKey): string =>
    isDefault.value ? '' : props.translations?.[field]?.[selected.value] || baseFor(field);

const placeholders = computed(() =>
    isDefault.value
        ? {}
        : {
              chat_window_title: placeholderFor('chat_window_title'),
              welcome_message: placeholderFor('welcome_message'),
              composer_placeholder: placeholderFor('composer_placeholder'),
          },
);

const update = (field: FieldKey, value: string): void => {
    if (isDefault.value) {
        if (field === 'welcome_message') emit('update:welcomeMessage', value);
        else if (field === 'chat_window_title') emit('update:chatWindowTitle', value);
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
    (['chat_window_title', 'welcome_message', 'composer_placeholder'] as FieldKey[]).some(
        (field) => (props.overrides?.[field]?.[code] ?? '').trim() !== '',
    );
</script>

<template>
    <div class="space-y-5">
        <!-- Language tabs (flags). The first tab edits the base text; each flag
             tab edits that language's override. -->
        <div class="border-b">
            <div class="-mb-px flex items-center gap-1 overflow-x-auto pb-px">
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="
                        isDefault
                            ? 'border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground border-transparent'
                    "
                    :title="t('agents.setup.sections.appearance_translations_default_tip')"
                    @click="selected = 'default'"
                >
                    <Globe class="h-4 w-4" />
                    {{ t('agents.setup.sections.appearance_translations_default') }}
                </button>
                <button
                    v-for="tab in tabs"
                    :key="tab.code"
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="
                        selected === tab.code
                            ? 'border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground border-transparent'
                    "
                    :title="tab.nativeName"
                    @click="selected = tab.code"
                >
                    <img :src="`/images/flags/${tab.flag}.svg`" :alt="tab.nativeName" class="h-4 w-5 rounded-[2px] object-cover" />
                    <span class="uppercase">{{ tab.code }}</span>
                    <span v-if="hasOverride(tab.code)" class="bg-primary h-1.5 w-1.5 rounded-full"></span>
                </button>
            </div>
        </div>

        <AppearanceTextFields
            :welcome-message="fieldValue('welcome_message')"
            :chat-window-title="fieldValue('chat_window_title')"
            :composer-placeholder="fieldValue('composer_placeholder')"
            :placeholders="placeholders"
            :errors="isDefault ? errors : {}"
            :disabled="disabled"
            @update:welcome-message="update('welcome_message', $event)"
            @update:chat-window-title="update('chat_window_title', $event)"
            @update:composer-placeholder="update('composer_placeholder', $event)"
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
