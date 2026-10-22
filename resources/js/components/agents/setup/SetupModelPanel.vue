<script setup lang="ts">
/**
 * Setup → Model: the model the agent answers with, from the ones an admin
 * made available, the answer temperature, and, for a model that reasons,
 * how hard it thinks first. That last one is locked at Off while the admin
 * does not let agents reason.
 */
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import type { ReasoningEffort } from '@/composables/agents/setup/useSetupModel';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Llm = Domain.Llm.Data.LlmModelData;

const props = defineProps<{
    catalog: Llm[];
    defaultModel: number | null;
    reasoningAllowed: boolean;
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

/** Only a model marked as reasoning can be told how hard to think. */
const modelReasons = computed(() => props.catalog.find((llm) => llm.id === form.model_id)?.is_reasoning ?? false);
</script>

<template>
    <div class="space-y-6 p-6">
        <div class="space-y-1">
            <h3 class="text-sm font-medium">{{ t('agents.setup.model_picker_title') }}</h3>
            <p class="text-muted-foreground text-sm">
                {{ t('agents.setup.model_picker_description') }}
            </p>
        </div>

        <div class="max-w-md space-y-2">
            <p v-if="!catalog.length" class="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                {{ t('agents.setup.model_empty') }}
            </p>
            <Select v-else v-model="form.model_id" :disabled="locked">
                <SelectTrigger class="w-full" :aria-label="t('agents.setup.model_picker_title')">
                    <SelectValue :placeholder="t('agents.setup.model_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="llm in catalog" :key="llm.id" :value="llm.id">
                        <span class="flex items-center gap-2">
                            {{ llm.name }}
                            <span class="text-muted-foreground text-xs">{{ llm.provider }}</span>
                            <Badge v-if="llm.id === defaultModel" variant="secondary" class="text-xs font-normal">
                                {{ t('agents.setup.model_default') }}
                            </Badge>
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.model_id" class="text-destructive text-sm">
                {{ form.errors.model_id }}
            </p>
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
