<script setup lang="ts">
/**
 * Icon button that opens a searchable grid of Heroicons and emits the picked
 * icon name (or null for "no icon"). Used for conversation-starter icons.
 */
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import HeroIcon from '@/components/common/HeroIcon.vue';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { heroiconNames, humanizeIconName } from '@/lib/heroicons';
import { Plus, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

withDefaults(
    defineProps<{
        modelValue?: string | null;
        disabled?: boolean;
    }>(),
    { modelValue: null, disabled: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const open = ref(false);
const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return heroiconNames;
    return heroiconNames.filter((name) => humanizeIconName(name).includes(term));
});

const select = (name: string | null) => {
    emit('update:modelValue', name);
    open.value = false;
};

// Reset the search each time the dialog opens.
watch(open, (isOpen) => {
    if (isOpen) search.value = '';
});
</script>

<template>
    <Dialog v-model:open="open">
        <Button
            type="button"
            variant="outline"
            size="icon"
            :disabled="disabled"
            class="shrink-0"
            :aria-label="modelValue ? t('shared.icon_picker.icon_aria', { name: humanizeIconName(modelValue) }) : t('shared.icon_picker.choose_icon')"
            @click="open = true"
        >
            <HeroIcon v-if="modelValue" :name="modelValue" class="h-4 w-4" />
            <Plus v-else class="text-muted-foreground h-4 w-4" />
        </Button>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('shared.icon_picker.choose_icon_title') }}</DialogTitle>
            </DialogHeader>

            <div class="relative">
                <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="search" :placeholder="t('shared.icon_picker.search_placeholder')" class="ps-9" />
            </div>

            <ScrollArea class="-mx-1 h-72 px-1">
                <div class="grid grid-cols-8 gap-1">
                    <!-- Clear / no icon -->
                    <button
                        type="button"
                        class="hover:bg-muted text-muted-foreground flex aspect-square items-center justify-center rounded-md border"
                        :class="modelValue ? 'border-transparent' : 'border-primary bg-primary/5'"
                        :aria-label="t('shared.icon_picker.no_icon')"
                        @click="select(null)"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <button
                        v-for="name in filtered"
                        :key="name"
                        type="button"
                        class="hover:bg-muted flex aspect-square items-center justify-center rounded-md border"
                        :class="name === modelValue ? 'border-primary bg-primary/5' : 'border-transparent'"
                        :title="humanizeIconName(name)"
                        :aria-label="humanizeIconName(name)"
                        @click="select(name)"
                    >
                        <HeroIcon :name="name" class="h-5 w-5" />
                    </button>
                </div>

                <p v-if="!filtered.length" class="text-muted-foreground py-8 text-center text-sm">{{ t('shared.icon_picker.no_results') }}</p>
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
