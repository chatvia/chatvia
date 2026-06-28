<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import HeroIcon from '@/components/ui/HeroIcon.vue';
import IconPicker from '@/components/ui/IconPicker.vue';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { LOCALES } from '@/i18n';
import axios from 'axios';
import { Globe, Info, Plus, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Starter {
    id?: string;
    text: string;
    icon: string | null;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

const props = withDefaults(
    defineProps<{
        starters: Starter[];
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

const emit = defineEmits<{ 'update:starters': [value: Starter[]] }>();

const { t } = useI18n();

// 'default' = base starters (text + icon); otherwise a locale code being translated.
const selected = ref<string>('default');
const isDefault = computed(() => selected.value === 'default');

const tabs = computed(() => {
    const allowed = Object.keys(props.languages).filter((code) => code !== 'auto');
    return allowed.length ? LOCALES.filter((l) => allowed.includes(l.code)) : LOCALES;
});

// Default tab shows one empty row to type into when there are no starters yet.
const rows = computed<Starter[]>(() => {
    if (props.disabled) return props.starters;
    return props.starters.length ? props.starters : [{ text: '', icon: null }];
});

const updateText = (index: number, value: string) => {
    const next = [...props.starters];
    next[index] = { ...(next[index] ?? { text: '', icon: null }), text: value };
    emit('update:starters', next);
};

const updateIcon = (index: number, icon: string | null) => {
    const next = [...props.starters];
    next[index] = { ...(next[index] ?? { text: '', icon: null }), icon };
    emit('update:starters', next);
};

const removeAt = (index: number) =>
    emit(
        'update:starters',
        props.starters.filter((_, i) => i !== index),
    );

const addRow = () => emit('update:starters', [...props.starters, { text: '', icon: null }]);

// ── Flag-tab translation editing ──────────────────────────────────────────
const overrideValue = (index: number, locale: string): string => props.starters[index]?.overrides?.[locale] ?? '';

// Placeholder = the automatic translation, falling back to the base text.
const overridePlaceholder = (index: number, locale: string): string =>
    props.starters[index]?.translations?.[locale] || props.starters[index]?.text || '';

const updateOverride = (index: number, locale: string, value: string) => {
    const next = props.starters.map((s) => ({ ...s, overrides: { ...(s.overrides ?? {}) } }));
    const overrides = next[index].overrides as Record<string, string>;
    if (value.trim() === '') delete overrides[locale];
    else overrides[locale] = value;
    emit('update:starters', next);
};

const hasOverride = (code: string): boolean => props.starters.some((s) => (s.overrides?.[code] ?? '').trim() !== '');

// ── AI generate ───────────────────────────────────────────────────────────
const genOpen = ref(false);
const genCount = ref('5');
const genLoading = ref(false);
const genSuggestions = ref<string[]>([]);
const genSelected = ref<string[]>([]);

const counts = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

const isAdded = (q: string) => props.starters.some((s) => s.text.trim() === q.trim());
const isSelected = (q: string) => genSelected.value.includes(q);

const generate = async () => {
    genLoading.value = true;
    try {
        const { data } = await axios.post(route('agents.starters.generate', { agent: props.agentExternalId }), {
            count: Number(genCount.value),
        });
        genSuggestions.value = Array.isArray(data?.suggestions) ? data.suggestions : [];
        genSelected.value = genSuggestions.value.filter((q) => !isAdded(q));
    } catch {
        toast.error(t('agents.setup.toasts.starters_error'));
    } finally {
        genLoading.value = false;
    }
};

const toggleSuggestion = (q: string) => {
    if (isAdded(q)) return;
    genSelected.value = isSelected(q) ? genSelected.value.filter((x) => x !== q) : [...genSelected.value, q];
};

const addSelected = () => {
    const merged = [...props.starters];
    for (const q of genSelected.value) {
        if (!merged.some((s) => s.text.trim() === q.trim())) merged.push({ text: q, icon: null });
    }
    emit('update:starters', merged);
    genOpen.value = false;
};

watch(genOpen, (open) => {
    if (open) {
        genSuggestions.value = [];
        genSelected.value = [];
        genCount.value = '5';
    }
});

const addLabel = computed(() =>
    genSelected.value.length
        ? t('agents.setup.sections.starters_add_count', { count: genSelected.value.length }, { plural: genSelected.value.length })
        : t('agents.setup.sections.starters_add_label'),
);
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
                @click="genOpen = true"
            >
                <Sparkles class="me-2 h-4 w-4" />
                {{ t('agents.setup.sections.starters_generate') }}
            </Button>
        </div>

        <!-- Language tabs (flags). Default edits the starters; each flag edits
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
                    :class="
                        selected === tab.code ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'
                    "
                    :title="tab.nativeName"
                    @click="selected = tab.code"
                >
                    <img :src="`/images/flags/${tab.flag}.svg`" :alt="tab.nativeName" class="h-4 w-5 rounded-[2px] object-cover" />
                    <span class="uppercase">{{ tab.code }}</span>
                    <span v-if="hasOverride(tab.code)" class="bg-primary h-1.5 w-1.5 rounded-full"></span>
                </button>
            </div>
        </div>

        <!-- Default tab: edit the starters (text + icon). -->
        <template v-if="isDefault">
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
        </template>

        <!-- Flag tab: translate each starter for the selected language. -->
        <template v-else>
            <div v-if="starters.length" class="space-y-3">
                <div v-for="(starter, index) in starters" :key="index" class="space-y-1">
                    <p class="text-muted-foreground truncate text-xs">{{ starter.text || t('agents.setup.sections.starters_empty') }}</p>
                    <Input
                        :model-value="overrideValue(index, selected)"
                        :placeholder="overridePlaceholder(index, selected)"
                        :disabled="disabled"
                        @update:model-value="updateOverride(index, selected, String($event ?? ''))"
                    />
                </div>
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.appearance_translations_lang_hint') }}</p>
            </div>
            <p v-else class="text-muted-foreground py-6 text-center text-sm">{{ t('agents.setup.sections.starters_translate_empty') }}</p>
        </template>

        <!-- AI generate dialog -->
        <Dialog v-model:open="genOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('agents.setup.sections.starters_generate_title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.setup.sections.starters_generate_description') }}</DialogDescription>
                </DialogHeader>

                <div class="flex items-end gap-2">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium">{{ t('agents.setup.sections.starters_generate_count') }}</label>
                        <Select v-model="genCount" :disabled="genLoading">
                            <SelectTrigger class="w-24"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in counts" :key="c" :value="c">{{ c }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="button" :disabled="genLoading" @click="generate">
                        <Sparkles class="me-2 h-4 w-4" />
                        {{ genLoading ? t('agents.setup.sections.starters_generating') : t('agents.setup.sections.starters_generate_button') }}
                    </Button>
                </div>

                <ScrollArea v-if="genSuggestions.length" class="-mx-1 h-72 px-1">
                    <div class="space-y-1">
                        <div
                            v-for="(q, index) in genSuggestions"
                            :key="index"
                            role="button"
                            :aria-disabled="isAdded(q)"
                            class="hover:bg-muted/50 flex w-full cursor-pointer items-start gap-3 rounded-md border p-3 text-start text-sm transition-colors"
                            :class="[
                                isSelected(q) ? 'border-primary bg-primary/5' : 'border-transparent',
                                isAdded(q) ? 'cursor-not-allowed opacity-60 hover:bg-transparent' : '',
                            ]"
                            @click="toggleSuggestion(q)"
                        >
                            <Checkbox :model-value="isAdded(q) || isSelected(q)" :disabled="isAdded(q)" class="pointer-events-none mt-0.5" />
                            <span class="flex-1">{{ q }}</span>
                        </div>
                    </div>
                </ScrollArea>

                <p v-else-if="!genLoading" class="text-muted-foreground py-8 text-center text-sm">
                    {{ t('agents.setup.sections.starters_generate_hint') }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="genOpen = false">{{ t('common.cancel') }}</Button>
                    <Button type="button" :disabled="!genSelected.length" @click="addSelected">{{ addLabel }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
