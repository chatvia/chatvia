<script setup lang="ts">
/**
 * The "Generate with AI" dialog of the Starters editor. It asks for a number
 * of starter ideas drawn from the agent's knowledge base and lets the owner
 * tick which ones to add. Ideas that already exist as starters are shown but
 * can't be picked. Nothing is saved here; the picked texts go to the parent.
 * When nothing can be made, the dialog says why (the server names the model
 * and what its provider said).
 */
import { generateStarters } from '@/dashboard/agents/api/agentSetup';
import { errorMessage } from '@/shared/api/http';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { ScrollArea } from '@/shared/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import { AlertCircle, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agentId: string;
    // Texts of the starters the agent already has.
    existing: string[];
}>();

const emit = defineEmits<{ add: [texts: string[]] }>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const COUNTS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

const count = ref('5');
const isLoading = ref(false);
const suggestions = ref<string[]>([]);
const picked = ref<string[]>([]);
// Why the last try made nothing: the server's reason, or '' when it gave none.
const failure = ref<string | null>(null);
const isEmpty = ref(false);

const isAdded = (text: string): boolean => props.existing.some((existing) => existing.trim() === text.trim());
const isPicked = (text: string): boolean => picked.value.includes(text);

// Start fresh every time the dialog opens.
watch(open, (isOpen) => {
    if (isOpen) {
        suggestions.value = [];
        picked.value = [];
        count.value = '5';
        failure.value = null;
        isEmpty.value = false;
    }
});

const generate = async (): Promise<void> => {
    isLoading.value = true;
    failure.value = null;
    isEmpty.value = false;
    try {
        const result = await generateStarters(props.agentId, Number(count.value));
        suggestions.value = Array.isArray(result?.suggestions) ? result.suggestions : [];
        picked.value = suggestions.value.filter((text) => !isAdded(text));
        isEmpty.value = suggestions.value.length === 0;
    } catch (error) {
        suggestions.value = [];
        picked.value = [];
        failure.value = errorMessage(error, '');
    } finally {
        isLoading.value = false;
    }
};

const toggle = (text: string): void => {
    if (isAdded(text)) return;
    picked.value = isPicked(text) ? picked.value.filter((x) => x !== text) : [...picked.value, text];
};

const addPicked = (): void => {
    emit('add', picked.value);
    open.value = false;
};

const addLabel = computed(() =>
    picked.value.length
        ? t('agents.setup.sections.starters_add_count', { count: picked.value.length }, { plural: picked.value.length })
        : t('agents.setup.sections.starters_add_label'),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('agents.setup.sections.starters_generate_title') }}</DialogTitle>
                <DialogDescription>{{ t('agents.setup.sections.starters_generate_description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex items-end gap-2">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium">{{ t('agents.setup.sections.starters_generate_count') }}</label>
                    <Select v-model="count" :disabled="isLoading">
                        <SelectTrigger class="w-24"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="c in COUNTS" :key="c" :value="c">{{ c }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button type="button" :disabled="isLoading" @click="generate">
                    <Sparkles class="me-2 h-4 w-4" />
                    {{ isLoading ? t('agents.setup.sections.starters_generating') : t('agents.setup.sections.starters_generate_button') }}
                </Button>
            </div>

            <ScrollArea v-if="suggestions.length" class="-mx-1 h-72 px-1">
                <div class="space-y-1">
                    <div
                        v-for="(text, index) in suggestions"
                        :key="index"
                        role="button"
                        :aria-disabled="isAdded(text)"
                        class="hover:bg-muted/50 flex w-full cursor-pointer items-start gap-3 rounded-md border p-3 text-start text-sm transition-colors"
                        :class="[
                            isPicked(text) ? 'border-primary bg-primary/5' : 'border-transparent',
                            isAdded(text) ? 'cursor-not-allowed opacity-60 hover:bg-transparent' : '',
                        ]"
                        @click="toggle(text)"
                    >
                        <Checkbox :model-value="isAdded(text) || isPicked(text)" :disabled="isAdded(text)" class="pointer-events-none mt-0.5" />
                        <span class="flex-1">{{ text }}</span>
                    </div>
                </div>
            </ScrollArea>

            <Alert v-else-if="failure !== null && !isLoading" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>{{ t('agents.setup.sections.starters_generate_failed') }}</AlertTitle>
                <AlertDescription v-if="failure" class="break-words">{{ failure }}</AlertDescription>
            </Alert>

            <p v-else-if="!isLoading" class="text-muted-foreground py-8 text-center text-sm">
                {{ isEmpty ? t('agents.setup.sections.starters_generate_empty') : t('agents.setup.sections.starters_generate_hint') }}
            </p>

            <DialogFooter>
                <Button type="button" variant="outline" @click="open = false">{{ t('common.cancel') }}</Button>
                <Button type="button" :disabled="!picked.length" @click="addPicked">{{ addLabel }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
