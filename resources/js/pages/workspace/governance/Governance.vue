<script setup lang="ts">
// Workspace tool-call audit log across all agents (the "Tool calls" tab of
// the Audit page), rendered by the shared AuditTable with an agent filter.
import AuditTable from '@/components/governance/AuditTable.vue';
import type { AuditPaginator } from '@/components/governance/types';
import AuditTabs from '@/components/governance/AuditTabs.vue';
import ExportCsvButton from '@/components/license/ExportCsvButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { audit, exportMethod } from '@/routes/governance';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    // Cursor paginator of audit rows.
    rows: AuditPaginator;
    agents: { id: number; name: string }[];
    connectors: { slug: string; name: string }[];
    outcomes: string[];
    filters: { outcome: string | null; agent_id: number | null; connector: string | null };
}

const props = defineProps<Props>();

const { t } = useI18n();

// The export takes the filters as they are applied (the page URL).
const exportUrl = computed(() =>
    exportMethod.url({
        query: {
            outcome: props.filters.outcome ?? undefined,
            connector: props.filters.connector ?? undefined,
            agent_id: props.filters.agent_id ?? undefined,
        },
    }),
);

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.governance.breadcrumb'), href: '/my/workspace/governance' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.audit.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <AuditTabs active="tools" />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{ t('workspace.governance.description') }}
                    </p>
                    <ExportCsvButton :href="exportUrl" />
                </div>

                <AuditTable
                    :rows="rows"
                    :outcomes="outcomes"
                    :connectors="connectors"
                    :agents="agents"
                    :show-agent="true"
                    :filters="filters"
                    :url="audit.url()"
                />
            </div>
        </WorkspaceLayout>
    </AppLayout>
</template>
