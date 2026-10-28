<script setup lang="ts">
// Per-agent tool-call audit log: the shared AuditTable scoped to this agent.
import AuditTable from '@/dashboard/components/governance/AuditTable.vue';
import type { AuditPaginator } from '@/dashboard/components/governance/types';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { audit } from '@/routes/agents';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    agent: { external_id: string; name: string };
    // Cursor paginator of audit rows.
    rows: AuditPaginator;
    connectors: { slug: string; name: string }[];
    outcomes: string[];
    filters: { outcome: string | null; connector: string | null };
}

const props = defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.audit.title')" :description="t('agents.audit.description')">
        <Head :title="t('agents.audit.head_title', { name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-6xl space-y-6">
                <AuditTable
                    :rows="props.rows"
                    :outcomes="props.outcomes"
                    :connectors="props.connectors"
                    :show-agent="false"
                    :filters="{ outcome: props.filters.outcome, connector: props.filters.connector, agent_id: null }"
                    :url="audit.url(props.agent.external_id)"
                />
            </div>
        </div>
    </AgentLayout>
</template>
