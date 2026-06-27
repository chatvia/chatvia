<script setup lang="ts">
/**
 * Editor for the widget's chat-bubble prompts. Mirrors StartersSection (minus
 * icons / AI-generate): a "Default" tab edits the base prompts and each flag tab
 * edits that language's translation (placeholder = the auto-translation, which
 * the override wins over). The parent saves via agents.bubbles.update.
 */
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LOCALES } from '@/i18n';
import { Globe, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Bubble {
    text: string;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

const props = withDefaults(
    defineProps<{
        bubbles: Bubble[];
        // Allowed language codes for the flag tabs (Setup → Languages).
        languages?: Record<string, string>;
        disabled?: boolean;
    }>(),
    { languages: () => ({}), disabled: false },
);

const emit = defineEmits<{ 'update:bubbles': [value: Bubble[]] }>();

const { t } = useI18n();

// 'default' = base prompts; otherwise a locale code being translated.
const selected = ref<string>('default');
const isDefault = computed(() => selected.value === 'default');

const tabs = computed(() => {
    const allowed = Object.keys(props.languages).filter((code) => code !== 'auto');
    return allowed.length ? LOCALES.filter((l) => allowed.includes(l.code)) : LOCALES;
});

// Default tab shows one empty row to type into when there are no bubbles yet.
const rows = computed<Bubble[]>(() => {
    if (props.disabled) return props.bubbles;
    return props.bubbles.length ? props.bubbles : [{ text: '' }];
});

const updateText = (index: number, value: string) => {
    const next = [...props.bubbles];
    next[index] = { ...(next[index] ?? { text: '' }), text: value };
    emit('update:bubbles', next);
};

const removeAt = (index: number) =>
    emit(
        'update:bubbles',
        props.bubbles.filter((_, i) => i !== index),
    );

const addRow = () => emit('update:bubbles', [...props.bubbles, { text: '' }]);

// ── Flag-tab translation editing ──────────────────────────────────────────
const overrideValue = (index: number, locale: string): string => props.bubbles[index]?.overrides?.[locale] ?? '';

// Placeholder = the automatic translation, falling back to the base text.
const overridePlaceholder = (index: number, locale: string): string =>
    props.bubbles[index]?.translations?.[locale] || props.bubbles[index]?.text || '';

const updateOverride = (index: number, locale: string, value: string) => {
    const next = props.bubbles.map((b) => ({ ...b, overrides: { ...(b.overrides ?? {}) } }));
    const overrides = next[index].overrides as Record<string, string>;
    if (value.trim() === '') delete overrides[locale];
    else overrides[locale] = value;
    emit('update:bubbles', next);
};

const hasOverride = (code: string): boolean => props.bubbles.some((b) => (b.overrides?.[code] ?? '').trim() !== '');
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-sm font-medium">{{ t('agents.setup.bubbles_title') }}</h3>
            <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.bubbles_description') }}</p>
        </div>

        <!-- Language tabs (flags). Default edits the prompts; each flag edits
             that language's translation. -->
        <div class="border-b">
            <div class="-mb-px flex items-center gap-1 overflow-x-auto pb-px">
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="isDefault ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'"
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
                    :class="selected === tab.code ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'"
                    :title="tab.nativeName"
                    @click="selected = tab.code"
                >
                    <img :src="`/images/flags/${tab.flag}.svg`" :alt="tab.nativeName" class="h-4 w-5 rounded-[2px] object-cover" />
                    <span class="uppercase">{{ tab.code }}</span>
                    <span v-if="hasOverride(tab.code)" class="bg-primary h-1.5 w-1.5 rounded-full"></span>
                </button>
            </div>
        </div>

        <!-- Default tab: edit the bubble prompts. -->
        <template v-if="isDefault">
            <div class="space-y-2">
                <div v-for="(bubble, index) in rows" :key="index" class="flex items-center gap-2">
                    <Input
                        :model-value="bubble.text"
                        :disabled="disabled"
                        :placeholder="t('agents.setup.bubbles_placeholder')"
                        class="flex-1"
                        @update:model-value="updateText(index, String($event ?? ''))"
                        @keydown.enter.prevent="addRow"
                    />
                    <Button
                        v-if="!disabled && bubbles.length > 0"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive shrink-0"
                        :aria-label="t('agents.setup.bubbles_remove')"
                        @click="removeAt(index)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <p v-if="disabled && !bubbles.length" class="text-muted-foreground text-sm">{{ t('agents.setup.bubbles_empty') }}</p>
            </div>

            <Button v-if="!disabled" type="button" variant="outline" size="sm" @click="addRow">
                <Plus class="me-2 h-4 w-4" />
                {{ t('agents.setup.bubbles_add') }}
            </Button>
        </template>

        <!-- Flag tab: translate each bubble for the selected language. -->
        <template v-else>
            <div v-if="bubbles.length" class="space-y-3">
                <div v-for="(bubble, index) in bubbles" :key="index" class="space-y-1">
                    <p class="text-muted-foreground truncate text-xs">{{ bubble.text || t('agents.setup.bubbles_empty') }}</p>
                    <Input
                        :model-value="overrideValue(index, selected)"
                        :placeholder="overridePlaceholder(index, selected)"
                        :disabled="disabled"
                        @update:model-value="updateOverride(index, selected, String($event ?? ''))"
                    />
                </div>
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.appearance_translations_lang_hint') }}</p>
            </div>
            <p v-else class="text-muted-foreground py-6 text-center text-sm">{{ t('agents.setup.bubbles_translate_empty') }}</p>
        </template>
    </div>
</template>
