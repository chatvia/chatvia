<script setup lang="ts">
// "Manage PII filters" dialog. It edits a draft copy of the enabled filters
// that is taken each time the dialog opens; only "Done" writes the draft back
// (escape / overlay / Cancel discard it).
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Check, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PiiFilterOption } from './types';

defineProps<{
    availableFilters: PiiFilterOption[];
    canManage: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const filters = defineModel<string[]>({ required: true });

const { t } = useI18n();

const draft = ref<string[]>([]);

watch(open, (isOpen) => {
    if (isOpen) draft.value = [...filters.value];
});

const toggleDraft = (key: string) => {
    const i = draft.value.indexOf(key);
    if (i === -1) draft.value.push(key);
    else draft.value.splice(i, 1);
};

const apply = () => {
    filters.value = [...draft.value];
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('agents.guardrails.pii_filters_title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('agents.guardrails.pii_filters_description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <div
                    v-for="filter in availableFilters"
                    :key="filter.key"
                    class="flex items-center justify-between gap-3 rounded-md border p-3"
                    :class="{ 'cursor-pointer': canManage }"
                    @click="canManage && toggleDraft(filter.key)"
                >
                    <Label class="pointer-events-none text-sm font-medium">{{ filter.label }}</Label>
                    <Switch :model-value="draft.includes(filter.key)" :disabled="!canManage" class="pointer-events-none" />
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">
                    <X class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.cancel') }}
                </Button>
                <Button type="button" @click="apply">
                    <Check class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.done') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
