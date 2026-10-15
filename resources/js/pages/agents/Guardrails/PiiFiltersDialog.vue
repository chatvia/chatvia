<script setup lang="ts">
// "Manage PII filters" dialog: which personal data to look for and whether to
// mask it or block the message, the owner's own patterns, and the values that
// are never masked (a support email, a phone number, a whole email domain).
// It edits draft copies taken each time the dialog opens; only "Done" writes
// them back (escape / overlay / Cancel discard them).
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Check, Plus, Regex, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { FILTER_LABELS, labelFor, PII_ACTION_LABELS } from './labels';
import type { CustomPattern, PiiAction, PiiFilterOption } from './types';

defineProps<{
    availableFilters: PiiFilterOption[];
    canManage: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const filters = defineModel<string[]>({ required: true });
const actions = defineModel<Record<string, PiiAction>>('actions', { required: true });
const patterns = defineModel<CustomPattern[]>('patterns', { required: true });
const allowlist = defineModel<string[]>('allowlist', { required: true });

const { t } = useI18n();

const ACTIONS: PiiAction[] = ['mask', 'block'];

const draft = ref<string[]>([]);
const actionDraft = ref<Record<string, PiiAction>>({});
const patternDraft = ref<CustomPattern[]>([]);
const allowDraft = ref<string[]>([]);

watch(open, (isOpen) => {
    if (isOpen) {
        draft.value = [...filters.value];
        actionDraft.value = { ...actions.value };
        patternDraft.value = patterns.value.map((pattern) => ({ ...pattern }));
        allowDraft.value = [...allowlist.value];
    }
});

const toggleDraft = (key: string) => {
    const i = draft.value.indexOf(key);
    if (i === -1) draft.value.push(key);
    else draft.value.splice(i, 1);
};

const actionOf = (key: string): PiiAction => actionDraft.value[key] ?? 'mask';
const setAction = (key: string, action: PiiAction) => {
    actionDraft.value = { ...actionDraft.value, [key]: action };
};

const addPattern = () => patternDraft.value.push({ name: '', pattern: '', action: 'mask' });
const removePattern = (index: number) => patternDraft.value.splice(index, 1);

const addValue = () => allowDraft.value.push('');
const removeValue = (index: number) => allowDraft.value.splice(index, 1);

const apply = () => {
    filters.value = [...draft.value];
    actions.value = { ...actionDraft.value };
    patterns.value = patternDraft.value
        .map((pattern) => ({ ...pattern, name: pattern.name.trim() }))
        .filter((pattern) => pattern.name !== '' && pattern.pattern !== '');
    allowlist.value = [...new Set(allowDraft.value.map((value) => value.trim()).filter(Boolean))];
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('agents.guardrails.pii_filters_title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('agents.guardrails.pii_filters_description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <div v-for="filter in availableFilters" :key="filter.key" class="flex items-center justify-between gap-3 rounded-md border p-3">
                    <div
                        class="flex min-w-0 flex-1 items-center gap-3"
                        :class="{ 'cursor-pointer': canManage }"
                        @click="canManage && toggleDraft(filter.key)"
                    >
                        <Switch :model-value="draft.includes(filter.key)" :disabled="!canManage" class="pointer-events-none" />
                        <Label class="pointer-events-none text-sm font-medium">{{ labelFor(t, FILTER_LABELS, filter.key) }}</Label>
                    </div>
                    <Select
                        v-if="draft.includes(filter.key)"
                        :model-value="actionOf(filter.key)"
                        :disabled="!canManage"
                        @update:model-value="(value) => setAction(filter.key, value as PiiAction)"
                    >
                        <SelectTrigger class="h-8 w-40" :aria-label="t('agents.guardrails.pii_action')">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="action in ACTIONS" :key="action" :value="action">{{ t(PII_ACTION_LABELS[action]) }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <p class="text-muted-foreground text-xs">{{ t('agents.guardrails.pii_action_help') }}</p>
            </div>

            <!-- Own patterns -->
            <div class="space-y-2 pt-2">
                <div>
                    <Label class="text-sm font-medium">{{ t('agents.guardrails.custom_patterns_title') }}</Label>
                    <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.custom_patterns_description') }}</p>
                </div>

                <div v-for="(pattern, index) in patternDraft" :key="index" class="space-y-2 rounded-md border p-3">
                    <div class="flex items-center gap-2">
                        <Input
                            v-model="pattern.name"
                            :disabled="!canManage"
                            :placeholder="t('agents.guardrails.custom_pattern_name_placeholder')"
                            :aria-label="t('agents.guardrails.custom_pattern_name')"
                        />
                        <Select v-model="pattern.action" :disabled="!canManage">
                            <SelectTrigger class="w-40 shrink-0" :aria-label="t('agents.guardrails.pii_action')">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="action in ACTIONS" :key="action" :value="action">{{ t(PII_ACTION_LABELS[action]) }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-destructive shrink-0"
                            :aria-label="t('agents.guardrails.remove_custom_pattern')"
                            @click="removePattern(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                    <div class="relative">
                        <Regex class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            v-model="pattern.pattern"
                            :disabled="!canManage"
                            dir="ltr"
                            class="ps-9 font-mono text-xs"
                            :placeholder="t('agents.guardrails.custom_pattern_placeholder')"
                            :aria-label="t('agents.guardrails.custom_pattern')"
                        />
                    </div>
                </div>

                <Button v-if="canManage" type="button" variant="outline" size="sm" class="w-fit" @click="addPattern">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.add_custom_pattern') }}
                </Button>
            </div>

            <!-- Never mask -->
            <div class="space-y-2 pt-2">
                <div>
                    <Label class="text-sm font-medium">{{ t('agents.guardrails.allowlist_title') }}</Label>
                    <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.allowlist_description') }}</p>
                </div>

                <div v-for="(_, index) in allowDraft" :key="index" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <ShieldCheck class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            v-model="allowDraft[index]"
                            :disabled="!canManage"
                            :placeholder="
                                index === 0 ? t('agents.guardrails.allowlist_placeholder_first') : t('agents.guardrails.allowlist_placeholder_more')
                            "
                            class="ps-9"
                            @keydown.enter.prevent="addValue"
                        />
                    </div>
                    <Button
                        v-if="canManage"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive shrink-0"
                        :aria-label="t('agents.guardrails.remove_allowlist_value')"
                        @click="removeValue(index)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <Button v-if="canManage" type="button" variant="outline" size="sm" class="w-fit" @click="addValue">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.add_allowlist_value') }}
                </Button>
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
