<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';
import Files from './Files.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.files')" :description="t('agents.sources.page_description.files')">
        <Files :agent="agent" />
    </AgentLayout>
</template>
