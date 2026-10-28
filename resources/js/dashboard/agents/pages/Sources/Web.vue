<script setup lang="ts">
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';
import WebComponent from './WebComponent.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.web')" :description="t('agents.sources.page_description.web')">
        <WebComponent :agent="agent" />
    </AgentLayout>
</template>
