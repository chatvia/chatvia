<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Info, Plus, Search, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        starters: string[];
        // Questions mined from the agent's knowledge-base chunks. Empty when the
        // agent has no ingested sources — the picker button stays hidden.
        knowledgeQuestions?: string[];
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { knowledgeQuestions: () => [], errors: () => ({}), disabled: false },
);

const emit = defineEmits<{
    'update:starters': [value: string[]];
}>();

// One editable field per starter. When the list is empty (and editable) we still
// show a single blank field so there is always somewhere to type.
const rows = computed(() => {
    if (props.disabled) return props.starters;
    return props.starters.length ? props.starters : [''];
});

const updateAt = (index: number, value: string) => {
    const next = [...props.starters];
    next[index] = value;
    emit('update:starters', next);
};

const removeAt = (index: number) => {
    emit(
        'update:starters',
        props.starters.filter((_, i) => i !== index),
    );
};

const addRow = () => emit('update:starters', [...props.starters, '']);

// ── Knowledge Base picker ────────────────────────────────────────────────
const dialogOpen = ref(false);
const search = ref('');
const selected = ref<string[]>([]);

// A question already used as a starter can't be picked again.
const isAdded = (q: string) => props.starters.includes(q);
const isSelected = (q: string) => selected.value.includes(q);

const filteredQuestions = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.knowledgeQuestions;
    return props.knowledgeQuestions.filter((q) => q.toLowerCase().includes(term));
});

const addLabel = computed(() => {
    if (!selected.value.length) return 'Add starters';
    return `Add ${selected.value.length} ${selected.value.length === 1 ? 'starter' : 'starters'}`;
});

const toggle = (q: string) => {
    if (isAdded(q)) return;
    selected.value = isSelected(q) ? selected.value.filter((x) => x !== q) : [...selected.value, q];
};

const addSelected = () => {
    if (!selected.value.length) return;
    const merged = [...props.starters];
    for (const q of selected.value) {
        if (!merged.includes(q)) merged.push(q);
    }
    emit('update:starters', merged);
    dialogOpen.value = false;
};

// Reset the transient picker state each time the dialog opens.
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
                <h3 class="text-sm font-medium">Conversation Starters</h3>
                <p class="text-muted-foreground mt-1 text-xs">Suggest questions to help users start conversations. One question per line.</p>
            </div>

            <Button
                v-if="!disabled && knowledgeQuestions.length"
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="dialogOpen = true"
            >
                <Sparkles class="mr-2 h-4 w-4" />
                Add from Knowledge Base
            </Button>
        </div>

        <div class="space-y-2">
            <div v-for="(starter, index) in rows" :key="index" class="flex items-center gap-2">
                <Input
                    :model-value="starter"
                    :disabled="disabled"
                    :placeholder="index === 0 ? 'E.g., How can I track my order?' : 'Add another conversation starter'"
                    class="flex-1"
                    @update:model-value="updateAt(index, String($event ?? ''))"
                    @keydown.enter.prevent="addRow"
                />
                <Button
                    v-if="!disabled && starters.length > 0"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="text-muted-foreground hover:text-destructive shrink-0"
                    aria-label="Remove starter"
                    @click="removeAt(index)"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>

            <p v-if="disabled && !starters.length" class="text-muted-foreground text-sm">No conversation starters yet.</p>
        </div>

        <Button v-if="!disabled" type="button" variant="outline" size="sm" @click="addRow">
            <Plus class="mr-2 h-4 w-4" />
            Add starter
        </Button>

        <p v-if="errors?.starters" class="text-destructive text-sm">{{ errors.starters }}</p>

        <Alert>
            <Info class="h-4 w-4" />
            <AlertDescription>Good starter messages are specific questions that showcase your agent's capabilities</AlertDescription>
        </Alert>

        <!-- Knowledge Base picker — suggested questions generated during ingestion. -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add from Knowledge Base</DialogTitle>
                    <DialogDescription>
                        Pick questions generated from this agent's knowledge. Selected questions become conversation starters.
                    </DialogDescription>
                </DialogHeader>

                <div class="relative">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search questions..." class="pl-9" />
                </div>

                <ScrollArea class="-mx-1 h-72 px-1">
                    <div class="space-y-1">
                        <div
                            v-for="(q, index) in filteredQuestions"
                            :key="index"
                            role="button"
                            :aria-disabled="isAdded(q)"
                            class="hover:bg-muted/50 flex w-full cursor-pointer items-start gap-3 rounded-md border p-3 text-left text-sm transition-colors"
                            :class="[
                                isSelected(q) ? 'border-primary bg-primary/5' : 'border-transparent',
                                isAdded(q) ? 'cursor-not-allowed opacity-60 hover:bg-transparent' : '',
                            ]"
                            @click="toggle(q)"
                        >
                            <Checkbox :model-value="isAdded(q) || isSelected(q)" :disabled="isAdded(q)" class="pointer-events-none mt-0.5" />
                            <span class="flex-1">{{ q }}</span>
                            <Badge v-if="isAdded(q)" variant="secondary" class="shrink-0">Added</Badge>
                        </div>

                        <p v-if="!filteredQuestions.length" class="text-muted-foreground py-8 text-center text-sm">No questions match your search.</p>
                    </div>
                </ScrollArea>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                    <Button type="button" :disabled="!selected.length" @click="addSelected">{{ addLabel }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
