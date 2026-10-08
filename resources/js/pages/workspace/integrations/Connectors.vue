<script lang="ts" setup>
import { connectorLogoUrl } from '@/lib/connectorLogo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AgentMcpServer, Connector, ConnectorRow } from '@/types/connector';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { LoaderCircle, Plug, Plus, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

import ConnectorDetailSheet from '@/components/connectors/ConnectorDetailSheet.vue';
import { useConnectorText } from '@/composables/useConnectorText';
import AddConnectorModal from './Connectors/AddConnectorModal.vue';
import SalesforceConnectModal from './Connectors/SalesforceConnectModal.vue';
import TokenConnectModal from './Connectors/TokenConnectModal.vue';

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.connectors.breadcrumb'), href: '/my/workspace/integrations/connectors' }];

const loading = ref(true);
const featured = ref<ConnectorRow[]>([]);
const custom = ref<ConnectorRow[]>([]);
const search = ref('');

const addOpen = ref(false);
const detailRow = ref<ConnectorRow | null>(null);
const detailOpen = ref(false);

const tokenConnectTarget = ref<Connector | null>(null);
const tokenConnectOpen = ref(false);
const salesforceTarget = ref<Connector | null>(null);
const salesforceOpen = ref(false);

function logoUrl(c: Connector | null): string | null {
    if (!c) return null;
    return connectorLogoUrl(c);
}

function matches(row: ConnectorRow): boolean {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    const name = (row.connector ? connectorName(row.connector) : (row.server?.name ?? '')).toLowerCase();
    const description = (row.connector ? connectorDescription(row.connector) : '').toLowerCase();
    return name.includes(q) || description.includes(q);
}

const enabledConnectors = computed(() => [...featured.value, ...custom.value].filter((r) => r.server?.is_enabled).filter(matches));
// Backend returns rows pre-sorted by ConnectorData.priority. Filter out enabled
// rows (they have their own section above) and keep that priority order.
const filteredFeatured = computed(() => featured.value.filter((r) => !r.server?.is_enabled).filter(matches));
const filteredCustom = computed(() => custom.value.filter((r) => !r.server?.is_enabled).filter(matches));
const totalCount = computed(() => featured.value.length + custom.value.length);

async function fetchAll(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get(route('integrations.connectors.data'));
        featured.value = response.data.featured;
        custom.value = response.data.custom;
    } catch (err) {
        console.error(err);
        toast.error(t('workspace.connectors.toast.load_failed'));
    } finally {
        loading.value = false;
    }
}

function isRowActionable(row: ConnectorRow): boolean {
    return row.connector === null || !row.connector.comingSoon;
}

function openRow(row: ConnectorRow): void {
    if (!isRowActionable(row)) return;
    detailRow.value = row;
    detailOpen.value = true;
}

async function startConnect(connector: Connector): Promise<void> {
    if (connector.comingSoon) return;

    if (connector.authStrategy === 'oauth') {
        // Per-workspace OAuth (no global app): collect the workspace's own OAuth
        // client first, then redirect. Otherwise a one-click platform-app redirect.
        if (connector.requiresClientId) {
            salesforceTarget.value = connector;
            salesforceOpen.value = true;
            return;
        }
        window.location.href = route('integrations.connectors.oauth.start', { slug: connector.slug });
        return;
    }

    if (connector.authStrategy === 'token') {
        tokenConnectTarget.value = connector;
        tokenConnectOpen.value = true;
        return;
    }

    // 'none' — no credentials, no modal. Just provision.
    try {
        const response = await axios.post(route('integrations.connectors.connect', { slug: connector.slug }));
        onTokenConnected(response.data.data);
        toast.success(t('workspace.connectors.toast.connected', { name: connectorName(connector) }));
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? t('workspace.connectors.toast.connect_failed', { name: connectorName(connector) }));
    }
}

function onTokenConnected(server: AgentMcpServer): void {
    const slug = server.connector_slug;
    if (slug) {
        const i = featured.value.findIndex((r) => r.connector?.slug === slug);
        if (i !== -1) featured.value[i] = { ...featured.value[i], server };
    }
    if (detailRow.value?.connector?.slug === slug) {
        detailRow.value = { ...detailRow.value, server };
    }
}

function onCustomAdded(server: AgentMcpServer): void {
    custom.value.unshift({ kind: 'custom', connector: null, server });
}

async function quickToggle(row: ConnectorRow): Promise<void> {
    if (!row.server) return;
    try {
        const response = await axios.post(route('integrations.connectors.enable', { slug: row.server.slug }));
        applyServerUpdate(response.data.data);
    } catch (err) {
        console.error(err);
        toast.error(t('workspace.connectors.toast.toggle_failed'));
    }
}

function applyServerUpdate(updated: AgentMcpServer): void {
    if (updated.connector_slug) {
        const i = featured.value.findIndex((r) => r.connector?.slug === updated.connector_slug);
        if (i !== -1) featured.value[i] = { ...featured.value[i], server: updated };
    } else {
        const i = custom.value.findIndex((r) => r.server?.external_id === updated.external_id);
        if (i !== -1) custom.value[i] = { ...custom.value[i], server: updated };
    }
    if (detailRow.value?.server?.external_id === updated.external_id) {
        detailRow.value = { ...detailRow.value, server: updated };
    }
}

function applyRemoval(slug: string): void {
    const f = featured.value.findIndex((r) => r.connector?.slug === slug);
    if (f !== -1) featured.value[f] = { ...featured.value[f], server: null };
    custom.value = custom.value.filter((r) => r.server?.slug !== slug);
}

onMounted(() => {
    fetchAll();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.connectors.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Plug class="size-6" />
                            {{ t('workspace.connectors.title') }}
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ t('workspace.connectors.description') }}
                        </p>
                    </div>
                    <Button @click="addOpen = true">
                        <Plus class="me-1 h-4 w-4" />
                        {{ t('workspace.connectors.add') }}
                    </Button>
                </div>

                <div class="relative max-w-sm">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" :placeholder="t('workspace.connectors.search_placeholder')" class="ps-9" />
                </div>

                <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('workspace.connectors.loading') }}
                </div>

                <div v-else-if="totalCount === 0" class="text-muted-foreground py-12 text-center text-sm">{{ t('workspace.connectors.empty') }}</div>

                <div v-else class="space-y-8">
                    <section v-if="enabledConnectors.length > 0">
                        <h2 class="mb-3 text-base font-semibold">{{ t('workspace.connectors.enabled_section') }}</h2>
                        <div class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="h-11 ps-5">{{ t('workspace.connectors.table.name') }}</TableHead>
                                        <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.tools') }}</TableHead>
                                        <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.agents') }}</TableHead>
                                        <TableHead class="h-11 w-32 pe-5 text-end">{{ t('workspace.connectors.table.enabled') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="row in enabledConnectors"
                                        :key="row.server!.external_id"
                                        class="hover:bg-muted/40 cursor-pointer"
                                        @click="openRow(row)"
                                    >
                                        <TableCell class="py-4 ps-5">
                                            <div class="flex items-center gap-4">
                                                <div
                                                    v-if="row.connector"
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                                    :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                                                >
                                                    <img :src="logoUrl(row.connector)!" :alt="row.connector.name" class="h-5 w-5" />
                                                </div>
                                                <div v-else class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                                    <Plug class="text-muted-foreground h-5 w-5" />
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="leading-tight font-medium">
                                                        {{ row.connector ? connectorName(row.connector) : row.server!.name }}
                                                    </p>
                                                    <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                                        {{ row.connector ? connectorDescription(row.connector) : row.server!.url }}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-4 text-sm">{{ row.server!.catalog_tools_count }}</TableCell>
                                        <TableCell class="py-4 text-sm">{{ row.server!.agents_count }}</TableCell>
                                        <TableCell class="py-4 pe-5 text-end" @click.stop>
                                            <Switch :model-value="row.server!.is_enabled" @update:model-value="quickToggle(row)" />
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <section v-if="filteredCustom.length > 0">
                        <h2 class="mb-3 text-base font-semibold">{{ t('workspace.connectors.custom_section') }}</h2>
                        <div class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="h-11 ps-5">{{ t('workspace.connectors.table.name') }}</TableHead>
                                        <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.tools') }}</TableHead>
                                        <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.agents') }}</TableHead>
                                        <TableHead class="h-11 w-32 pe-5 text-end">{{ t('workspace.connectors.table.enabled') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="row in filteredCustom"
                                        :key="row.server!.external_id"
                                        class="hover:bg-muted/40 cursor-pointer"
                                        @click="openRow(row)"
                                    >
                                        <TableCell class="py-4 ps-5">
                                            <div class="flex items-center gap-4">
                                                <div class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                                    <Plug class="text-muted-foreground h-5 w-5" />
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="leading-tight font-medium">{{ row.server!.name }}</p>
                                                    <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ row.server!.url }}</p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-4 text-sm">{{ row.server!.catalog_tools_count }}</TableCell>
                                        <TableCell class="py-4 text-sm">{{ row.server!.agents_count }}</TableCell>
                                        <TableCell class="py-4 pe-5 text-end" @click.stop>
                                            <Switch :model-value="row.server!.is_enabled" @update:model-value="quickToggle(row)" />
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <section>
                        <h2 class="mb-3 text-base font-semibold">
                            {{ t('workspace.connectors.available_section') }}
                            <span class="text-muted-foreground ms-1 text-sm font-normal">({{ filteredFeatured.length }})</span>
                        </h2>
                        <div v-if="filteredFeatured.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                            {{ t('workspace.connectors.no_results') }}
                        </div>
                        <div v-else class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="h-11 ps-5">{{ t('workspace.connectors.table.name') }}</TableHead>
                                        <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.tools') }}</TableHead>
                                        <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.agents') }}</TableHead>
                                        <TableHead class="h-11 w-40 pe-5 text-end">{{ t('workspace.connectors.table.enabled') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="row in filteredFeatured"
                                        :key="row.connector!.slug"
                                        :class="isRowActionable(row) ? 'hover:bg-muted/40 cursor-pointer' : 'cursor-default'"
                                        @click="openRow(row)"
                                    >
                                        <TableCell class="py-4 ps-5">
                                            <div class="flex items-center gap-4">
                                                <div
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                                    :style="{ backgroundColor: '#' + row.connector!.brandColor + '15' }"
                                                >
                                                    <img :src="logoUrl(row.connector)!" :alt="row.connector!.name" class="h-5 w-5" />
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="leading-tight font-medium">{{ connectorName(row.connector!) }}</p>
                                                    <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                                        {{ connectorDescription(row.connector!) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-4 text-sm">{{ row.server?.catalog_tools_count ?? '—' }}</TableCell>
                                        <TableCell class="py-4 text-sm">{{ row.server?.agents_count ?? '—' }}</TableCell>
                                        <TableCell class="py-4 pe-5 text-end" @click.stop>
                                            <Switch v-if="row.server" :model-value="row.server.is_enabled" @update:model-value="quickToggle(row)" />
                                            <Button
                                                v-else-if="!row.connector!.comingSoon"
                                                size="sm"
                                                variant="outline"
                                                @click="startConnect(row.connector!)"
                                            >
                                                {{ t('workspace.common.connect') }}
                                            </Button>
                                            <Badge v-else variant="outline">{{ t('workspace.connectors.coming_soon') }}</Badge>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                </div>
            </div>

            <AddConnectorModal v-model:open="addOpen" @added="onCustomAdded" />

            <ConnectorDetailSheet
                v-model:open="detailOpen"
                mode="settings"
                :row="detailRow"
                @updated="applyServerUpdate"
                @removed="applyRemoval"
                @connect-token="startConnect"
                @connect-oauth="startConnect"
                @agents-changed="fetchAll"
            />

            <TokenConnectModal v-model:open="tokenConnectOpen" :connector="tokenConnectTarget" @connected="onTokenConnected" />

            <SalesforceConnectModal v-model:open="salesforceOpen" :connector="salesforceTarget" />
        </WorkspaceLayout>
    </AppLayout>
</template>
