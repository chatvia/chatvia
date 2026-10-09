<script setup lang="ts" generic="T extends TranslatableItem">
/**
 * The flag-tab body of the Starters and Bubbles editors: one input per item
 * for the selected language. The placeholder is the AI translation (else the
 * base text); typing a value stores a manual edit that wins over it.
 */
import { Input } from '@/components/ui/input';
import { fallbackOf, overrideOf, withOverride, type TranslatableItem } from '@/composables/agents/setup/translatableItems';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    items: T[];
    locale: string;
    disabled?: boolean;
    // Shown above an item whose base text is empty.
    emptyItemLabel: string;
    // Shown instead of the list when there are no items yet.
    emptyListLabel: string;
}>();

const emit = defineEmits<{ 'update:items': [items: T[]] }>();

const { t } = useI18n();

const update = (index: number, value: string): void => emit('update:items', withOverride(props.items, index, props.locale, value));
</script>

<template>
    <div v-if="items.length" class="space-y-3">
        <div v-for="(item, index) in items" :key="index" class="space-y-1">
            <p class="text-muted-foreground truncate text-xs">{{ item.text || emptyItemLabel }}</p>
            <Input
                :model-value="overrideOf(items, index, locale)"
                :placeholder="fallbackOf(items, index, locale)"
                :disabled="disabled"
                @update:model-value="update(index, String($event ?? ''))"
            />
        </div>
        <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.appearance_translations_lang_hint') }}</p>
    </div>
    <p v-else class="text-muted-foreground py-6 text-center text-sm">{{ emptyListLabel }}</p>
</template>
