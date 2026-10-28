<script setup lang="ts">
/** Setup → Starters: the conversation starters shown in an empty chat. */
import StartersSection from '@/dashboard/agents/components/sections/StartersSection.vue';
import SetupSaveFooter from '@/dashboard/agents/components/setup/SetupSaveFooter.vue';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';

defineProps<{
    agentId: string;
    hasKnowledgeBase: boolean;
}>();

const { locked, starters: section, languages } = useSetupContext();
const { starters, isSaving, save } = section;
const { enabledLanguageMap } = languages;
</script>

<template>
    <div class="space-y-6 p-6">
        <StartersSection
            v-model:starters="starters"
            :languages="enabledLanguageMap"
            :has-knowledge-base="hasKnowledgeBase"
            :agent-external-id="agentId"
            :disabled="locked"
        />
        <SetupSaveFooter :saving="isSaving" @save="save" />
    </div>
</template>
