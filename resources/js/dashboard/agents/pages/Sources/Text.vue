<script setup lang="ts">
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';
import TextComponent from './TextComponent.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.text')" :description="t('agents.sources.page_description.text')">
        <TextComponent :agent="agent" />
    </AgentLayout>
</template>
