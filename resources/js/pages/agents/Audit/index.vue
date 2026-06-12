<script setup lang="ts">
import AuditTable from '@/components/governance/AuditTable.vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    agent: { external_id: string; name: string };
    // Laravel paginator of audit rows — shape validated by AuditTable.
    rows: any;
    connectors: { slug: string; name: string }[];
    outcomes: string[];
    filters: { outcome: string | null; connector: string | null };
}

const props = defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.audit.title')">
        <Head :title="t('agents.audit.head_title', { name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-6xl space-y-6">
                <p class="text-muted-foreground text-sm">
                    {{ t('agents.audit.description') }}
                </p>

                <AuditTable
                    :rows="props.rows"
                    :outcomes="props.outcomes"
                    :connectors="props.connectors"
                    :show-agent="false"
                    :filters="{ outcome: props.filters.outcome, connector: props.filters.connector, agent_id: null }"
                    route-name="agents.audit"
                    :route-params="{ agent: props.agent.external_id }"
                />
            </div>
        </div>
    </AgentLayout>
</template>
