<script setup lang="ts">
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';
import Files from './Files.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.video')" :description="t('agents.sources.page_description.video')">
        <Files :agent="agent" file-type="video" />
    </AgentLayout>
</template>
