<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';
import QaComponent from './QaComponent.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.qa')" :description="t('agents.sources.page_description.qa')">
        <QaComponent :agent="agent" />
    </AgentLayout>
</template>
