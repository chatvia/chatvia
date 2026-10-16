<script setup lang="ts">
// Security → Blocked: who cannot chat with this agent (blocked visitors and
// switched-off chatters). The same list as the Guardrails "Blocked" tab,
// where people are blocked from the Activity log.
import BlockedList from '@/components/security/BlockedList.vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { UserX } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    agent: { external_id: string; name: string };
}>();

const { t } = useI18n();
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.security.blocked.title')">
        <Head :title="t('agents.security.blocked.head_title', { name: agent.name })" />

        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <UserX class="size-6" />
                        {{ t('agents.security.blocked.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('agents.security.blocked.subtitle') }}</p>
                </div>

                <BlockedList :agent-id="agent.external_id" />
            </div>
        </div>
    </AgentLayout>
</template>
