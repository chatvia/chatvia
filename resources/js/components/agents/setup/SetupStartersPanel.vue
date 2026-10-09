<script setup lang="ts">
/** Setup → Starters: the conversation starters shown in an empty chat. */
import StartersSection from '@/components/agents/sections/StartersSection.vue';
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';

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
