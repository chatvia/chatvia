<script setup lang="ts">
/** Setup → Model: the answer temperature (the model itself is managed by the platform). */
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { locked, model } = useSetupContext();
const { form, isSaving, save } = model;
</script>

<template>
    <div class="space-y-6 p-6">
        <div class="space-y-1">
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
            <input
                v-model.number="form.temperature"
                type="range"
                min="0"
                max="2"
                step="0.1"
                :disabled="locked"
                class="accent-primary h-2 w-full cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
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

        <SetupSaveFooter divided :saving="isSaving" @save="save" />
    </div>
</template>
