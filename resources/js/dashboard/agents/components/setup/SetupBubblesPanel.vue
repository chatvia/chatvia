<script setup lang="ts">
/** Setup → Chat Bubbles (Widget only): quick-message prompts next to the widget launcher. */
import BubblesSection from '@/dashboard/agents/components/sections/BubblesSection.vue';
import SetupSaveFooter from '@/dashboard/agents/components/setup/SetupSaveFooter.vue';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';

const { locked, bubbles: section, languages } = useSetupContext();
const { bubbles, isSaving, save } = section;
const { enabledLanguageMap } = languages;
</script>

<template>
    <div class="space-y-6 p-6">
        <BubblesSection v-model:bubbles="bubbles" :languages="enabledLanguageMap" :disabled="locked" />
        <SetupSaveFooter divided :saving="isSaving" @save="save" />
    </div>
</template>
