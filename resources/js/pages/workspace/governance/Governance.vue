<script setup lang="ts">
import AuditTable from '@/components/governance/AuditTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Props {
    // Laravel paginator of audit rows — shape validated by AuditTable.
    rows: any;
    agents: { id: number; name: string }[];
    connectors: { slug: string; name: string }[];
    outcomes: string[];
    filters: { outcome: string | null; agent_id: number | null; connector: string | null };
}

defineProps<Props>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.governance.breadcrumb'), href: '/my/workspace/governance' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.governance.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ShieldCheck class="size-6" />
                        {{ t('workspace.governance.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ t('workspace.governance.description') }}
                    </p>
                </div>

                <AuditTable
                    :rows="rows"
                    :outcomes="outcomes"
                    :connectors="connectors"
                    :agents="agents"
                    :show-agent="true"
                    :filters="filters"
                    route-name="settings.governance"
                />
            </div>
        </WorkspaceLayout>
    </AppLayout>
</template>
