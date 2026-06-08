<script setup lang="ts">
import AuditTable from '@/components/governance/AuditTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';

interface Props {
    // Laravel paginator of audit rows — shape validated by AuditTable.
    rows: any;
    agents: { id: number; name: string }[];
    connectors: { slug: string; name: string }[];
    outcomes: string[];
    filters: { outcome: string | null; agent_id: number | null; connector: string | null };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Governance', href: '/settings/governance' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Governance" />

        <SettingsLayout width="wide">
            <div class="w-full space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ShieldCheck class="size-6" />
                        Audit log
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Every action your agents take through tools and connectors — allowed, approved, denied, or rate-limited — with the arguments,
                        result, and who triggered it.
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
        </SettingsLayout>
    </AppLayout>
</template>
