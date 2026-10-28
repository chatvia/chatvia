<script setup lang="ts">
// Security → Blocked: who cannot chat with this agent (blocked visitors and
// switched-off chatters). The same list as the Guardrails "Blocked" tab,
// where people are blocked from the Activity log.
import BlockedList from '@/dashboard/agents/components/security/BlockedList.vue';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    agent: { external_id: string; name: string };
}>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.security.blocked.title')" :description="t('agents.security.blocked.subtitle')">
        <Head :title="t('agents.security.blocked.head_title', { name: agent.name })" />

        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <BlockedList :agent-id="agent.external_id" />
            </div>
        </div>
    </AgentLayout>
</template>
