<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';
import YoutubeComponent from './YoutubeComponent.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const _props = defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.youtube')">
        <YoutubeComponent :agent="agent" />
    </AgentLayout>
</template>
