<script setup lang="ts">
/**
 * Setup → Model: the model the agent answers with, from the ones an admin
 * made available (grouped by the provider that serves them, each under the
 * name and maker's logo the hosted chat shows, with the provider's id
 * below; a model that cannot call tools is marked, as the agent then
 * answers without tools), the answer
 * temperature, and, for a model that reasons, how hard it thinks first. That
 * last one is locked at Off while the admin does not let agents reason.
 *
 * Multi-model (shown while the admin lets agents offer it) turns the picker
 * into a multi-select: chatters then pick among the chosen models in the
 * hosted chat, the widget and the command bar. The first one chosen is the
 * agent's own model, which answers until a chatter picks another.
 */
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import ChatModelLogo from '@/components/chat/ChatModelLogo.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectSeparator, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import type { ReasoningEffort } from '@/composables/agents/setup/useSetupModel';
import { ChevronDown, Info, Server, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Llm = Domain.Llm.Data.LlmModelData;

/** The models one provider serves, under its name and logo. */
type ProviderGroup = { provider: string; icon: string | null; models: Llm[] };

const props = defineProps<{
    catalog: Llm[];
    defaultModel: number | null;
    reasoningAllowed: boolean;
    modelChoiceAllowed: boolean;
}>();

const { t } = useI18n();
const { locked, model } = useSetupContext();
const { form, isSaving, save } = model;

const EFFORTS: ReasoningEffort[] = ['off', 'low', 'medium', 'high'];

const effortLabels = computed<Record<ReasoningEffort, string>>(() => ({
    off: t('agents.setup.reasoning_off'),
    low: t('agents.setup.reasoning_low'),
    medium: t('agents.setup.reasoning_medium'),
    high: t('agents.setup.reasoning_high'),
}));

/** The catalog by provider, in the catalog's order. */
const groups = computed<ProviderGroup[]>(() => {
    const byProvider = new Map<string, ProviderGroup>();

    for (const llm of props.catalog) {
        const group = byProvider.get(llm.provider) ?? { provider: llm.provider, icon: llm.provider_icon, models: [] };
        group.models.push(llm);
        byProvider.set(llm.provider, group);
    }

    return [...byProvider.values()];
});

const selectedModel = computed(() => props.catalog.find((llm) => llm.id === form.model_id));

/** The name people know a model by (models.dev, as the hosted chat shows it), else the provider's id. */
const modelName = (llm: Llm): string => llm.display_name ?? llm.name;

/** Only a model marked as reasoning can be told how hard to think. */
const modelReasons = computed(() => selectedModel.value?.is_reasoning ?? false);

/** The switch shows while the admin lets agents offer a choice, or while it is still on from before. */
const showsMultiModel = computed(() => props.modelChoiceAllowed || form.model_choice_enabled);

/** The chosen models with Multi-model on: the agent's own first, then the others in the order they were chosen. */
const chosenIds = computed<number[]>(() => [
    ...(form.model_id !== null ? [form.model_id] : []),
    ...form.offered_model_ids.filter((id) => id !== form.model_id),
]);

const chosenModels = computed(() =>
    chosenIds.value.map((id) => props.catalog.find((llm) => llm.id === id)).filter((llm): llm is Llm => llm !== undefined),
);

/** Ticks or unticks a model. Unticking the agent's own hands its place to the next one; the last one stays. */
function choose(llm: Llm, on: boolean): void {
    const others = form.offered_model_ids.filter((id) => id !== form.model_id && id !== llm.id);

    if (on) {
        if (form.model_id === null) form.model_id = llm.id;
        else if (llm.id !== form.model_id) others.push(llm.id);
    } else if (llm.id === form.model_id) {
        if (others.length === 0) return;
        form.model_id = others.shift() ?? null;
    }

    form.offered_model_ids = others;
}
</script>

<template>
    <div class="space-y-6 p-6">
        <div class="flex max-w-md items-start justify-between gap-4">
            <div class="space-y-1">
                <h3 class="text-sm font-medium">{{ t('agents.setup.model_picker_title') }}</h3>
                <p class="text-muted-foreground text-sm">
                    {{ t('agents.setup.model_picker_description') }}
                </p>
            </div>
            <div v-if="showsMultiModel && catalog.length" class="flex shrink-0 items-center gap-1.5">
                <Label for="multi-model" class="text-sm font-normal">{{ t('agents.setup.multi_model') }}</Label>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button type="button" class="text-muted-foreground hover:text-foreground" :aria-label="t('agents.setup.multi_model_help')">
                            <Info class="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent class="max-w-xs">{{ t('agents.setup.multi_model_help') }}</TooltipContent>
                </Tooltip>
                <Switch
                    id="multi-model"
                    v-model="form.model_choice_enabled"
                    :disabled="locked || (!modelChoiceAllowed && !form.model_choice_enabled)"
                />
            </div>
        </div>

        <div class="max-w-md space-y-2">
            <p v-if="!catalog.length" class="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                {{ t('agents.setup.model_empty') }}
            </p>
            <DropdownMenu v-else-if="form.model_choice_enabled">
                <DropdownMenuTrigger as-child :disabled="locked">
                    <button
                        type="button"
                        class="border-input dark:bg-input/30 flex min-h-9 w-full items-center gap-2 rounded-md border bg-transparent px-3 py-2 text-start text-sm shadow-xs disabled:cursor-not-allowed disabled:opacity-50"
                        :aria-label="t('agents.setup.model_picker_title')"
                    >
                        <span v-if="chosenModels.length" class="flex min-w-0 flex-1 flex-wrap gap-1">
                            <Badge v-for="llm in chosenModels" :key="llm.id" variant="secondary" class="gap-1 font-normal" :title="llm.name">
                                <ChatModelLogo :logo="llm.logo" />
                                {{ modelName(llm) }}
                            </Badge>
                        </span>
                        <span v-else class="text-muted-foreground flex-1">{{ t('agents.setup.multi_model_placeholder') }}</span>
                        <ChevronDown class="size-4 shrink-0 opacity-50" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="max-h-80 w-(--reka-dropdown-menu-trigger-width) overflow-y-auto">
                    <template v-for="(group, index) in groups" :key="group.provider">
                        <DropdownMenuSeparator v-if="index > 0" />
                        <DropdownMenuLabel class="text-muted-foreground flex items-center gap-2 text-xs font-normal">
                            <img v-if="group.icon" :src="group.icon" alt="" class="size-4 shrink-0" />
                            <Server v-else class="size-4 shrink-0" />
                            {{ group.provider }}
                        </DropdownMenuLabel>
                        <DropdownMenuCheckboxItem
                            v-for="llm in group.models"
                            :key="llm.id"
                            :model-value="chosenIds.includes(llm.id)"
                            @update:model-value="(on: boolean) => choose(llm, on)"
                            @select.prevent
                        >
                            <ChatModelLogo :logo="llm.logo" />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate">{{ modelName(llm) }}</span>
                                <span v-if="llm.display_name" class="text-muted-foreground truncate text-xs">{{ llm.name }}</span>
                            </span>
                            <span v-if="!llm.calls_tools" class="text-muted-foreground text-xs">
                                {{ t('agents.setup.model_no_tools') }}
                            </span>
                        </DropdownMenuCheckboxItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
            <Select v-else v-model="form.model_id" :disabled="locked">
                <SelectTrigger class="w-full" :aria-label="t('agents.setup.model_picker_title')">
                    <SelectValue :placeholder="t('agents.setup.model_placeholder')">
                        <span v-if="selectedModel" class="flex min-w-0 items-center gap-2" :title="selectedModel.name">
                            <ChatModelLogo :logo="selectedModel.logo" />
                            <span class="truncate">{{ modelName(selectedModel) }}</span>
                        </span>
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <template v-for="(group, index) in groups" :key="group.provider">
                        <SelectSeparator v-if="index > 0" />
                        <SelectGroup>
                            <SelectLabel class="text-muted-foreground flex items-center gap-2 text-xs">
                                <img v-if="group.icon" :src="group.icon" alt="" class="size-4 shrink-0" />
                                <Server v-else class="size-4 shrink-0" />
                                {{ group.provider }}
                            </SelectLabel>
                            <SelectItem v-for="llm in group.models" :key="llm.id" :value="llm.id" class="ps-8">
                                <ChatModelLogo :logo="llm.logo" />
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate">{{ modelName(llm) }}</span>
                                    <span v-if="llm.display_name" class="text-muted-foreground truncate text-xs">{{ llm.name }}</span>
                                </span>
                                <Badge v-if="llm.id === defaultModel" variant="secondary" class="text-xs font-normal">
                                    {{ t('agents.setup.model_default') }}
                                </Badge>
                                <span v-if="!llm.calls_tools" class="text-muted-foreground text-xs">
                                    {{ t('agents.setup.model_no_tools') }}
                                </span>
                            </SelectItem>
                        </SelectGroup>
                    </template>
                </SelectContent>
            </Select>
            <p v-if="form.model_choice_enabled && catalog.length" class="text-muted-foreground text-sm">
                {{ t('agents.setup.multi_model_hint') }}
            </p>
            <p v-if="form.errors.model_id" class="text-destructive text-sm">
                {{ form.errors.model_id }}
            </p>
            <p v-if="form.errors.model_choice_enabled" class="text-destructive text-sm">{{ form.errors.model_choice_enabled }}</p>
            <p v-if="form.errors.offered_model_ids" class="text-destructive text-sm">{{ form.errors.offered_model_ids }}</p>
            <Alert
                v-if="selectedModel && !selectedModel.calls_tools"
                class="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <Wrench />
                <AlertTitle>{{ t('agents.setup.model_no_tools_title') }}</AlertTitle>
                <AlertDescription class="text-amber-800 dark:text-amber-300">
                    {{ t('agents.setup.model_no_tools_description') }}
                </AlertDescription>
            </Alert>
        </div>

        <div class="space-y-1 border-t pt-6">
            <h3 class="text-sm font-medium">{{ t('agents.setup.model_title') }}</h3>
            <p class="text-muted-foreground text-sm">
                {{ t('agents.setup.model_description') }}
            </p>
        </div>

        <div class="max-w-md space-y-3">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium">{{ t('agents.setup.temperature') }}</span>
                <span class="text-muted-foreground tabular-nums">{{ form.temperature.toFixed(1) }}</span>
            </div>
            <Slider
                :model-value="[form.temperature]"
                :min="0"
                :max="2"
                :step="0.1"
                :disabled="locked"
                :aria-label="t('agents.setup.temperature')"
                @update:model-value="(value) => value && (form.temperature = value[0])"
            />
            <div class="text-muted-foreground flex justify-between text-xs">
                <span>{{ t('agents.setup.temperature_precise') }}</span>
                <span>{{ t('agents.setup.temperature_balanced') }}</span>
                <span>{{ t('agents.setup.temperature_creative') }}</span>
            </div>
            <p v-if="form.errors.temperature" class="text-destructive text-sm">
                {{ form.errors.temperature }}
            </p>
        </div>

        <div v-if="modelReasons" class="max-w-md space-y-2 border-t pt-6">
            <h3 class="text-sm font-medium">{{ t('agents.setup.reasoning_title') }}</h3>
            <p class="text-muted-foreground text-sm">{{ t('agents.setup.reasoning_description') }}</p>
            <Select v-model="form.reasoning_effort" :disabled="locked || !reasoningAllowed">
                <SelectTrigger class="w-full" :aria-label="t('agents.setup.reasoning_title')">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="effort in EFFORTS" :key="effort" :value="effort">
                        {{ effortLabels[effort] }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="!reasoningAllowed" class="text-muted-foreground text-sm">{{ t('agents.setup.reasoning_locked') }}</p>
            <p v-if="form.errors.reasoning_effort" class="text-destructive text-sm">
                {{ form.errors.reasoning_effort }}
            </p>
        </div>

        <SetupSaveFooter divided :saving="isSaving" :disabled="!catalog.length" @save="save" />
    </div>
</template>
