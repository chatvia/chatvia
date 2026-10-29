<script setup lang="ts">
/**
 * Editor for the agent's conversation starters. The "Default" tab edits the
 * starters (text + icon); each flag tab edits one language's translation
 * (placeholder = the AI translation, which a manual edit wins over). Agents
 * with knowledge-base content can also get AI suggestions. The parent saves
 * via agents.starters.update.
 */
import type { StarterItem } from '@/dashboard/agents/api/agentSetup';
import HeroIcon from '@/shared/components/HeroIcon.vue';
import IconPicker from '@/dashboard/agents/components/IconPicker.vue';
import ItemTranslationsEditor from '@/dashboard/agents/components/setup/ItemTranslationsEditor.vue';
import StarterSuggestionsDialog from '@/dashboard/agents/components/setup/StarterSuggestionsDialog.vue';
import TranslationLanguageTabs from '@/dashboard/agents/components/setup/TranslationLanguageTabs.vue';
import { Alert, AlertDescription } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { hasAnyOverride } from '@/dashboard/agents/composables/setup/translatableItems';
import { LOCALES } from '@/shared/i18n';
import { Info, Plus, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        starters: StarterItem[];
        // Allowed language codes (config('chatvia.languages')) for the flag tabs.
        languages?: Record<string, string>;
        // Whether the agent has knowledge-base content (gates the AI button).
        hasKnowledgeBase?: boolean;
        agentExternalId: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { languages: () => ({}), hasKnowledgeBase: false, errors: () => ({}), disabled: false },
);

const emit = defineEmits<{ 'update:starters': [value: StarterItem[]] }>();

const { t } = useI18n();

// 'default' = base starters (text + icon); otherwise a locale code being translated.
const selected = ref<string>('default');
const isDefault = computed(() => selected.value === 'default');

// Default tab shows one empty row to type into when there are no starters yet.
const rows = computed<StarterItem[]>(() => {
    if (props.disabled) return props.starters;
    return props.starters.length ? props.starters : [{ text: '', icon: null }];
});

const updateAt = (index: number, change: Partial<StarterItem>): void => {
    const next = [...props.starters];
    next[index] = { ...(next[index] ?? { text: '', icon: null }), ...change };
    emit('update:starters', next);
};

const removeAt = (index: number): void =>
    emit(
        'update:starters',
        props.starters.filter((_, i) => i !== index),
    );

const addRow = (): void => emit('update:starters', [...props.starters, { text: '', icon: null }]);

const hasOverride = (code: string): boolean => hasAnyOverride(props.starters, code);

// ── AI suggestions ────────────────────────────────────────────────────────
const isSuggesting = ref(false);
const existingTexts = computed(() => props.starters.map((starter) => starter.text));
// Same languages as the flag tabs: the allowed ones, or every language when none are set.
const languageCount = computed(() => Object.keys(props.languages).filter((code) => code !== 'auto').length || LOCALES.length);

const addSuggestions = (texts: string[]): void => {
    const merged = [...props.starters];
    for (const text of texts) {
        if (!merged.some((starter) => starter.text.trim() === text.trim())) merged.push({ text, icon: null });
    }
    emit('update:starters', merged);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-sm font-medium">{{ t('agents.setup.sections.starters_title') }}</h3>
                <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.sections.starters_description') }}</p>
            </div>

            <Button
                v-if="!disabled && hasKnowledgeBase && isDefault"
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="isSuggesting = true"
            >
                <Sparkles class="me-2 h-4 w-4" />
                {{ t('agents.setup.sections.starters_generate') }}
            </Button>
        </div>

        <TranslationLanguageTabs v-model="selected" :languages="languages" :has-override="hasOverride" />

        <!-- Default tab: edit the starters (text + icon). -->
        <template v-if="isDefault">
            <div class="space-y-2">
                <div v-for="(starter, index) in rows" :key="index" class="flex items-center gap-2">
                    <IconPicker v-if="!disabled" :model-value="starter.icon" @update:model-value="updateAt(index, { icon: $event })" />
                    <span v-else-if="starter.icon" class="text-muted-foreground flex h-9 w-9 shrink-0 items-center justify-center">
                        <HeroIcon :name="starter.icon" class="h-4 w-4" />
                    </span>

                    <Input
                        :model-value="starter.text"
                        :disabled="disabled"
                        :placeholder="
                            index === 0 ? t('agents.setup.sections.starters_placeholder_first') : t('agents.setup.sections.starters_placeholder_more')
                        "
                        class="flex-1"
                        @update:model-value="updateAt(index, { text: String($event ?? '') })"
                        @keydown.enter.prevent="addRow"
                    />
                    <Button
                        v-if="!disabled && starters.length > 0"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive shrink-0"
                        :aria-label="t('agents.setup.sections.starters_remove')"
                        @click="removeAt(index)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <p v-if="disabled && !starters.length" class="text-muted-foreground text-sm">{{ t('agents.setup.sections.starters_empty') }}</p>
            </div>

            <Button v-if="!disabled" type="button" variant="outline" size="sm" @click="addRow">
                <Plus class="me-2 h-4 w-4" />
                {{ t('agents.setup.sections.starters_add') }}
            </Button>

            <p v-if="errors?.starters" class="text-destructive text-sm">{{ errors.starters }}</p>

            <Alert>
                <Info class="h-4 w-4" />
                <AlertDescription>{{ t('agents.setup.sections.starters_tip') }}</AlertDescription>
            </Alert>
        </template>

        <!-- Flag tab: translate each starter for the selected language. -->
        <ItemTranslationsEditor
            v-else
            :items="starters"
            :locale="selected"
            :disabled="disabled"
            :empty-item-label="t('agents.setup.sections.starters_empty')"
            :empty-list-label="t('agents.setup.sections.starters_translate_empty')"
            @update:items="emit('update:starters', $event)"
        />

        <StarterSuggestionsDialog
            v-model:open="isSuggesting"
            :agent-id="agentExternalId"
            :existing="existingTexts"
            :language-count="languageCount"
            @add="addSuggestions"
        />
    </div>
</template>
