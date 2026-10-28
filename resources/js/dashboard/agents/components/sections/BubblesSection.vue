<script setup lang="ts">
/**
 * Editor for the widget's chat-bubble prompts. Mirrors StartersSection (minus
 * icons / AI-generate): a "Default" tab edits the base prompts and each flag tab
 * edits that language's translation (placeholder = the auto-translation, which
 * the override wins over). The parent saves via agents.bubbles.update.
 */
import type { BubbleItem } from '@/dashboard/agents/api/agentSetup';
import ItemTranslationsEditor from '@/dashboard/agents/components/setup/ItemTranslationsEditor.vue';
import TranslationLanguageTabs from '@/dashboard/agents/components/setup/TranslationLanguageTabs.vue';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { hasAnyOverride } from '@/dashboard/agents/composables/setup/translatableItems';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        bubbles: BubbleItem[];
        // Allowed language codes for the flag tabs (Setup → Languages).
        languages?: Record<string, string>;
        disabled?: boolean;
    }>(),
    { languages: () => ({}), disabled: false },
);

const emit = defineEmits<{ 'update:bubbles': [value: BubbleItem[]] }>();

const { t } = useI18n();

// 'default' = base prompts; otherwise a locale code being translated.
const selected = ref<string>('default');
const isDefault = computed(() => selected.value === 'default');

// Default tab shows one empty row to type into when there are no bubbles yet.
const rows = computed<BubbleItem[]>(() => {
    if (props.disabled) return props.bubbles;
    return props.bubbles.length ? props.bubbles : [{ text: '' }];
});

const updateText = (index: number, value: string): void => {
    const next = [...props.bubbles];
    next[index] = { ...(next[index] ?? { text: '' }), text: value };
    emit('update:bubbles', next);
};

const removeAt = (index: number): void =>
    emit(
        'update:bubbles',
        props.bubbles.filter((_, i) => i !== index),
    );

const addRow = (): void => emit('update:bubbles', [...props.bubbles, { text: '' }]);

const hasOverride = (code: string): boolean => hasAnyOverride(props.bubbles, code);
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-sm font-medium">{{ t('agents.setup.bubbles_title') }}</h3>
            <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.bubbles_description') }}</p>
        </div>

        <TranslationLanguageTabs v-model="selected" :languages="languages" :has-override="hasOverride" />

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
        <ItemTranslationsEditor
            v-else
            :items="bubbles"
            :locale="selected"
            :disabled="disabled"
            :empty-item-label="t('agents.setup.bubbles_empty')"
            :empty-list-label="t('agents.setup.bubbles_translate_empty')"
            @update:items="emit('update:bubbles', $event)"
        />
    </div>
</template>
