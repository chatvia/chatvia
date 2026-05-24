<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { AgentMcpServer, Connector, ConnectorRow, McpTool } from '@/types/connector';
import axios from 'axios';
import { CheckCircle2, ChevronRight, LoaderCircle, Plug, RefreshCw, Unplug } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
    agentId: string;
    row: ConnectorRow | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    updated: [server: AgentMcpServer];
    removed: [slug: string];
    'connect-token': [connector: Connector];
    'connect-oauth': [connector: Connector];
}>();

const activeTab = ref<'overview' | 'tools'>('overview');
const tools = ref<McpTool[]>([]);
const toolsLoading = ref(false);
const refreshing = ref(false);
const expanded = ref<Record<string, boolean>>({});
const disconnectOpen = ref(false);

const connector = computed(() => props.row?.connector ?? null);
const server = computed(() => props.row?.server ?? null);
const displayName = computed(() => connector.value?.name ?? server.value?.name ?? '');
const description = computed(() => connector.value?.description ?? 'Custom MCP server added directly via URL.');
const isConnected = computed(() => server.value !== null);
const isCustom = computed(() => props.row?.kind === 'custom');
const brandColor = computed(() => connector.value?.brandColor ?? '6B7280');
const logo = computed(() => {
    if (!connector.value) return null;
    return connector.value.logoUrl ?? `https://cdn.simpleicons.org/${connector.value.icon}/${connector.value.brandColor}`;
});

watch(
    () => [props.open, props.row?.server?.slug, activeTab.value],
    async () => {
        if (!props.open || !server.value || activeTab.value !== 'tools') return;
        if (tools.value.length > 0 && !toolsLoading.value) return; // already loaded
        await loadTools();
    },
);

watch(
    () => props.row?.server?.slug,
    () => {
        tools.value = [];
        expanded.value = {};
        activeTab.value = 'overview';
    },
);

async function loadTools(): Promise<void> {
    if (!server.value) return;
    toolsLoading.value = true;
    try {
        const response = await axios.get(route('agents.sources.connectors.tools', { agent: props.agentId, slug: server.value.slug }));
        tools.value = response.data.tools;
    } catch (err) {
        console.error(err);
        toast.error('Failed to load tools');
    } finally {
        toolsLoading.value = false;
    }
}

async function refreshTools(): Promise<void> {
    if (!server.value || !connector.value) return;
    refreshing.value = true;
    try {
        const response = await axios.post(route('agents.sources.connectors.refresh', { agent: props.agentId, slug: connector.value.slug }));
        emit('updated', response.data.data);
        await loadTools();
        toast.success(`${displayName.value}: ${response.data.tools_count} tools`);
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? 'Failed to refresh tools');
    } finally {
        refreshing.value = false;
    }
}

async function toggleEnabled(): Promise<void> {
    if (!server.value) return;
    try {
        const response = await axios.post(route('agents.sources.connectors.enable', { agent: props.agentId, slug: server.value.slug }));
        emit('updated', response.data.data);
    } catch (err) {
        console.error(err);
        toast.error('Failed to toggle');
    }
}

function askDisconnect(): void {
    // The Sheet sits at z-50, which is the same as Dialog. Rather than
    // duel z-indexes, close the sheet first so the confirmation dialog
    // owns the full screen on its own.
    emit('update:open', false);
    // Wait a tick so the sheet exit animation doesn't overlap.
    setTimeout(() => {
        disconnectOpen.value = true;
    }, 150);
}

async function confirmDisconnect(): Promise<void> {
    if (!server.value) return;
    const slug = isCustom.value ? server.value.slug : (connector.value?.slug ?? server.value.slug);
    try {
        await axios.delete(route('agents.sources.connectors.disconnect', { agent: props.agentId, slug }));
        emit('removed', slug);
        emit('update:open', false);
        toast.success(`Disconnected ${displayName.value}`);
    } catch (err) {
        console.error(err);
        toast.error('Failed to disconnect');
    } finally {
        disconnectOpen.value = false;
    }
}

function startConnect(): void {
    if (!connector.value) return;
    if (connector.value.authStrategy === 'oauth') {
        emit('connect-oauth', connector.value);
    } else {
        emit('connect-token', connector.value);
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent v-if="row" class="w-full overflow-y-auto p-0 sm:max-w-md">
            <SheetHeader class="space-y-5 border-b px-6 pt-7 pb-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg" :style="{ backgroundColor: '#' + brandColor + '15' }">
                        <img v-if="logo" :src="logo" :alt="displayName" class="h-7 w-7" />
                        <Plug v-else class="text-muted-foreground h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1 pt-1">
                        <SheetTitle class="truncate text-lg">{{ displayName }}</SheetTitle>
                        <div class="mt-1.5 flex items-center gap-1.5 text-sm">
                            <CheckCircle2 v-if="isConnected" class="h-4 w-4 text-emerald-600" />
                            <span class="text-muted-foreground">
                                {{ isConnected ? 'Connected' : 'Not connected' }}
                            </span>
                        </div>
                    </div>
                </div>
                <SheetDescription class="text-sm leading-relaxed">
                    {{ description }}
                </SheetDescription>
            </SheetHeader>

            <Tabs v-model="activeTab" class="px-6 pt-5">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="tools" :disabled="!isConnected">Tools</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-6 pt-6">
                    <div v-if="!isConnected && connector">
                        <p v-if="connector.comingSoon" class="text-muted-foreground text-sm">This connector is coming soon.</p>
                        <Button v-else class="w-full" @click="startConnect">
                            <Plug class="mr-2 h-4 w-4" />
                            {{ connector.authStrategy === 'oauth' ? 'Sign in with ' + connector.name : 'Connect ' + connector.name }}
                        </Button>
                    </div>

                    <dl v-if="isConnected" class="divide-border divide-y text-sm">
                        <div class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                            <dt class="text-muted-foreground col-span-1">Endpoint</dt>
                            <dd class="col-span-2 font-mono text-xs leading-relaxed break-all">{{ server?.url }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">Auth</dt>
                            <dd class="col-span-2 capitalize">{{ server?.auth_type }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">Tools</dt>
                            <dd class="col-span-2">{{ server?.catalog_tools_count }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">Calls</dt>
                            <dd class="col-span-2">{{ server?.invocation_count ?? 0 }}</dd>
                        </div>
                        <div v-if="server?.last_invoked_at" class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">Last call</dt>
                            <dd class="col-span-2">{{ new Date(server.last_invoked_at).toLocaleString() }}</dd>
                        </div>
                        <div v-if="server?.last_error" class="grid grid-cols-3 gap-4 py-3 last:pb-0">
                            <dt class="text-muted-foreground col-span-1">Last error</dt>
                            <dd class="text-destructive col-span-2">{{ server.last_error }}</dd>
                        </div>
                    </dl>

                    <div v-if="isConnected" class="space-y-4 border-t pt-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Enabled</span>
                            <Button size="sm" :variant="server?.enabled ? 'default' : 'outline'" class="min-w-16" @click="toggleEnabled">
                                {{ server?.enabled ? 'On' : 'Off' }}
                            </Button>
                        </div>
                        <Button variant="destructive" class="w-full" @click="askDisconnect">
                            <Unplug class="mr-2 h-4 w-4" />
                            Disconnect
                        </Button>
                    </div>
                </TabsContent>

                <TabsContent value="tools" class="space-y-4 pt-6">
                    <Button variant="outline" class="w-full" :disabled="refreshing" @click="refreshTools">
                        <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                        Refresh tools
                    </Button>

                    <div v-if="toolsLoading" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        Loading tools
                    </div>

                    <div v-else-if="tools.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                        No tools advertised by this server.
                    </div>

                    <div v-else>
                        <div class="text-muted-foreground mb-3 flex items-center gap-2 text-xs">
                            <span>Interactive tools</span>
                            <Badge variant="secondary">{{ tools.length }}</Badge>
                        </div>
                        <div class="divide-border divide-y">
                            <div v-for="tool in tools" :key="tool.name" class="py-4 first:pt-0 last:pb-0">
                                <p class="font-medium">{{ tool.short_name }}</p>
                                <p class="text-muted-foreground mt-1.5 text-sm leading-relaxed">{{ tool.description }}</p>
                                <button
                                    class="text-muted-foreground hover:text-foreground mt-3 inline-flex items-center gap-1 text-xs"
                                    @click="expanded[tool.name] = !expanded[tool.name]"
                                >
                                    <ChevronRight class="h-3 w-3 transition-transform" :class="{ 'rotate-90': expanded[tool.name] }" />
                                    View tool input schema
                                </button>
                                <pre v-if="expanded[tool.name]" class="bg-muted mt-2 overflow-x-auto rounded p-3 text-xs">{{
                                    JSON.stringify(tool.parameters, null, 2)
                                }}</pre>
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>

            <div class="h-6"></div>
        </SheetContent>
    </Sheet>

    <DeleteConfirmationDialog
        :open="disconnectOpen"
        :title="`Disconnect ${displayName}?`"
        description="The chat will no longer be able to call tools from this connector. You can reconnect anytime."
        confirm-text="Disconnect"
        cancel-text="Keep connected"
        @cancel="disconnectOpen = false"
        @confirm="confirmDisconnect"
    />
</template>
