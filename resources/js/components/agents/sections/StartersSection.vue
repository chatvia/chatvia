<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import HeroIcon from '@/components/ui/HeroIcon.vue';
import IconPicker from '@/components/ui/IconPicker.vue';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Info, Plus, Search, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Starter {
    text: string;
    icon: string | null;
}

const props = withDefaults(
    defineProps<{
        starters: Starter[];
        knowledgeQuestions?: string[];
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { knowledgeQuestions: () => [], errors: () => ({}), disabled: false },
);

const emit = defineEmits<{
    'update:starters': [value: Starter[]];
}>();

const { t } = useI18n();

const rows = computed<Starter[]>(() => {
    if (props.disabled) return props.starters;
    return props.starters.length ? props.starters : [{ text: '', icon: null }];
});

const updateText = (index: number, value: string) => {
    const next = [...props.starters];
    next[index] = { text: value, icon: next[index]?.icon ?? null };
    emit('update:starters', next);
};

const updateIcon = (index: number, icon: string | null) => {
    const next = [...props.starters];
    next[index] = { text: next[index]?.text ?? '', icon };
    emit('update:starters', next);
};

const removeAt = (index: number) => {
    emit(
        'update:starters',
        props.starters.filter((_, i) => i !== index),
    );
};

const addRow = () => emit('update:starters', [...props.starters, { text: '', icon: null }]);

const dialogOpen = ref(false);
const search = ref('');
const selected = ref<string[]>([]);

const isAdded = (q: string) => props.starters.some((s) => s.text === q);
const isSelected = (q: string) => selected.value.includes(q);

const filteredQuestions = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.knowledgeQuestions;
    return props.knowledgeQuestions.filter((q) => q.toLowerCase().includes(term));
});

const addLabel = computed(() => {
    if (!selected.value.length) return t('agents.setup.sections.starters_add_label');
    return t('agents.setup.sections.starters_add_count', { count: selected.value.length }, { plural: selected.value.length });
});

const toggle = (q: string) => {
    if (isAdded(q)) return;
    selected.value = isSelected(q) ? selected.value.filter((x) => x !== q) : [...selected.value, q];
};

const addSelected = () => {
    if (!selected.value.length) return;
    const merged = [...props.starters];
    for (const q of selected.value) {
        if (!merged.some((s) => s.text === q)) merged.push({ text: q, icon: null });
    }
    emit('update:starters', merged);
    dialogOpen.value = false;
};

watch(dialogOpen, (open) => {
    if (open) {
        selected.value = [];
        search.value = '';
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-sm font-medium">{{ t('agents.setup.sections.starters_title') }}</h3>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ t('agents.setup.sections.starters_description') }}
                </p>
            </div>

            <Button
                v-if="!disabled && knowledgeQuestions.length"
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="dialogOpen = true"
            >
                <Sparkles class="me-2 h-4 w-4" />
                {{ t('agents.setup.sections.starters_from_kb') }}
            </Button>
        </div>

        <div class="space-y-2">
            <div v-for="(starter, index) in rows" :key="index" class="flex items-center gap-2">
                <IconPicker v-if="!disabled" :model-value="starter.icon" @update:model-value="updateIcon(index, $event)" />
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
                    @update:model-value="updateText(index, String($event ?? ''))"
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

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('agents.setup.sections.starters_dialog_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.setup.sections.starters_dialog_description') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="relative">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" :placeholder="t('agents.setup.sections.starters_search')" class="ps-9" />
                </div>

                <ScrollArea class="-mx-1 h-72 px-1">
                    <div class="space-y-1">
                        <div
                            v-for="(q, index) in filteredQuestions"
                            :key="index"
                            role="button"
                            :aria-disabled="isAdded(q)"
                            class="hover:bg-muted/50 flex w-full cursor-pointer items-start gap-3 rounded-md border p-3 text-start text-sm transition-colors"
                            :class="[
                                isSelected(q) ? 'border-primary bg-primary/5' : 'border-transparent',
                                isAdded(q) ? 'cursor-not-allowed opacity-60 hover:bg-transparent' : '',
                            ]"
                            @click="toggle(q)"
                        >
                            <Checkbox :model-value="isAdded(q) || isSelected(q)" :disabled="isAdded(q)" class="pointer-events-none mt-0.5" />
                            <span class="flex-1">{{ q }}</span>
                            <Badge v-if="isAdded(q)" variant="secondary" class="shrink-0">{{ t('agents.setup.sections.starters_added') }}</Badge>
                        </div>

                        <p v-if="!filteredQuestions.length" class="text-muted-foreground py-8 text-center text-sm">
                            {{ t('agents.setup.sections.starters_no_match') }}
                        </p>
                    </div>
                </ScrollArea>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="dialogOpen = false">{{ t('common.cancel') }}</Button>
                    <Button type="button" :disabled="!selected.length" @click="addSelected">{{ addLabel }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
