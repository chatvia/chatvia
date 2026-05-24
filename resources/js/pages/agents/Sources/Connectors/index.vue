<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { AgentMcpServer, Connector, ConnectorRow } from '@/types/connector';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { LoaderCircle, Plug, Plus, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AddConnectorModal from './AddConnectorModal.vue';
import ConnectorDetailSheet from './ConnectorDetailSheet.vue';
import TokenConnectModal from './TokenConnectModal.vue';

interface Props {
    agent: { external_id: string; name: string };
}

const props = defineProps<Props>();

const loading = ref(true);
const featured = ref<ConnectorRow[]>([]);
const custom = ref<ConnectorRow[]>([]);
const search = ref('');

const addOpen = ref(false);
const detailRow = ref<ConnectorRow | null>(null);
const detailOpen = ref(false);

const tokenConnectTarget = ref<Connector | null>(null);
const tokenConnectOpen = ref(false);

function logoUrl(c: Connector | null): string | null {
    if (!c) return null;
    // Catalog entries can supply an explicit `logo_url` (e.g. a local SVG);
    // otherwise we fall back to the brand mark from the simple-icons CDN.
    return c.logoUrl ?? `https://cdn.simpleicons.org/${c.icon}/${c.brandColor}`;
}

function matches(row: ConnectorRow): boolean {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    const name = (row.connector?.name ?? row.server?.name ?? '').toLowerCase();
    const description = (row.connector?.description ?? '').toLowerCase();
    return name.includes(q) || description.includes(q);
}

const filteredFeatured = computed(() => featured.value.filter(matches));
const filteredCustom = computed(() => custom.value.filter(matches));

const totalCount = computed(() => featured.value.length + custom.value.length);

async function fetchAll(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get(route('agents.sources.connectors.data', props.agent.external_id));
        featured.value = response.data.featured;
        custom.value = response.data.custom;
    } catch (err) {
        console.error(err);
        toast.error('Failed to load connectors');
    } finally {
        loading.value = false;
    }
}

function relativeTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    const ms = Date.now() - new Date(iso).getTime();
    const day = 86_400_000;
    const hour = 3_600_000;
    const minute = 60_000;
    if (ms < minute) return 'just now';
    if (ms < hour) return Math.floor(ms / minute) + 'm ago';
    if (ms < day) return Math.floor(ms / hour) + 'h ago';
    const days = Math.floor(ms / day);
    if (days < 30) return days + 'd ago';
    const months = Math.floor(days / 30);
    if (months < 12) return months + 'mo ago';
    return Math.floor(months / 12) + 'y ago';
}

function openRow(row: ConnectorRow): void {
    detailRow.value = row;
    detailOpen.value = true;
}

async function startConnect(connector: Connector): Promise<void> {
    if (connector.comingSoon) return;

    if (connector.authStrategy === 'oauth') {
        window.location.href = route('agents.sources.connectors.oauth.start', {
            agent: props.agent.external_id,
            slug: connector.slug,
        });
        return;
    }

    if (connector.authStrategy === 'token') {
        tokenConnectTarget.value = connector;
        tokenConnectOpen.value = true;
        return;
    }

    // 'none' — no credentials, no modal. Just provision.
    try {
        const response = await axios.post(
            route('agents.sources.connectors.connect', { agent: props.agent.external_id, slug: connector.slug }),
        );
        onTokenConnected(response.data.data);
        toast.success(`Connected ${connector.name}`);
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? `Failed to connect ${connector.name}`);
    }
}

function onTokenConnected(server: AgentMcpServer): void {
    // Patch local state so the table updates without a full refetch.
    const slug = server.connector_slug;
    if (slug) {
        const i = featured.value.findIndex((r) => r.connector?.slug === slug);
        if (i !== -1) featured.value[i] = { ...featured.value[i], server };
    }
    // Refresh the detail row if it's currently open
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
        const response = await axios.post(route('agents.sources.connectors.enable', { agent: props.agent.external_id, slug: row.server.slug }));
        applyServerUpdate(response.data.data);
    } catch (err) {
        console.error(err);
        toast.error('Failed to toggle');
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

function flashFromSession(): void {
    const flash = (usePage().props as { flash?: { success?: string | null; error?: string | null } }).flash;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}

onMounted(() => {
    flashFromSession();
    fetchAll();
});
</script>

<template>
    <AgentLayout :agent="agent">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">Connectors</h1>
                        <Badge variant="secondary">Public preview</Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        Plug third-party services into the chat. Pick from the catalog or add any MCP server by URL.
                    </p>
                </div>
                <Button @click="addOpen = true">
                    <Plus class="mr-1 h-4 w-4" />
                    Add connector
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input v-model="search" placeholder="Search connectors" class="pl-9" />
            </div>

            <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                <LoaderCircle class="h-4 w-4 animate-spin" />
                Loading connectors
            </div>

            <div v-else-if="totalCount === 0" class="text-muted-foreground py-12 text-center text-sm">No connectors yet.</div>

            <div v-else class="space-y-8">
                <section v-if="filteredCustom.length > 0">
                    <h2 class="mb-3 text-base font-semibold">Custom connectors</h2>
                    <div class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="h-11 pl-5">Connector name</TableHead>
                                    <TableHead class="h-11 w-32">Tools</TableHead>
                                    <TableHead class="h-11 w-32">Created</TableHead>
                                    <TableHead class="h-11 w-32 pr-5 text-right">Enabled</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in filteredCustom"
                                    :key="row.server!.external_id"
                                    class="hover:bg-muted/40 cursor-pointer"
                                    @click="openRow(row)"
                                >
                                    <TableCell class="py-4 pl-5">
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
                                    <TableCell class="text-muted-foreground py-4 text-sm">
                                        {{ relativeTime(row.server!.catalog_cached_at) }}
                                    </TableCell>
                                    <TableCell class="py-4 pr-5 text-right" @click.stop>
                                        <Switch :model-value="row.server!.enabled" @update:model-value="quickToggle(row)" />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section v-if="filteredFeatured.length > 0">
                    <h2 class="mb-3 text-base font-semibold">Featured connectors</h2>
                    <div class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="h-11 pl-5">Connector name</TableHead>
                                    <TableHead class="h-11 w-32">Authentications</TableHead>
                                    <TableHead class="h-11 w-32">Created</TableHead>
                                    <TableHead class="h-11 w-40 pr-5 text-right">Enabled</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in filteredFeatured"
                                    :key="row.connector!.slug"
                                    class="hover:bg-muted/40 cursor-pointer"
                                    @click="openRow(row)"
                                >
                                    <TableCell class="py-4 pl-5">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                                :style="{ backgroundColor: '#' + row.connector!.brandColor + '15' }"
                                            >
                                                <img :src="logoUrl(row.connector)!" :alt="row.connector!.name" class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="leading-tight font-medium">{{ row.connector!.name }}</p>
                                                <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                                    {{ row.connector!.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-4 text-sm">{{ row.server ? 1 : 0 }}</TableCell>
                                    <TableCell class="text-muted-foreground py-4 text-sm">
                                        {{ relativeTime(row.server?.catalog_cached_at) }}
                                    </TableCell>
                                    <TableCell class="py-4 pr-5 text-right" @click.stop>
                                        <Switch v-if="row.server" :model-value="row.server.enabled" @update:model-value="quickToggle(row)" />
                                        <Button
                                            v-else-if="!row.connector!.comingSoon"
                                            size="sm"
                                            variant="outline"
                                            @click="startConnect(row.connector!)"
                                        >
                                            Connect
                                        </Button>
                                        <Badge v-else variant="outline">Soon</Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>

        <AddConnectorModal v-model:open="addOpen" :agent-id="agent.external_id" @added="onCustomAdded" />

        <ConnectorDetailSheet
            v-model:open="detailOpen"
            :agent-id="agent.external_id"
            :row="detailRow"
            @updated="applyServerUpdate"
            @removed="applyRemoval"
            @connect-token="startConnect"
            @connect-oauth="startConnect"
        />

        <TokenConnectModal
            v-model:open="tokenConnectOpen"
            :agent-id="agent.external_id"
            :connector="tokenConnectTarget"
            @connected="onTokenConnected"
        />
    </AgentLayout>
</template>
