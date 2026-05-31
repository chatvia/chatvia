<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import type { AgentMcpServer, Connector, McpTool } from '@/types/connector';
import axios from 'axios';
import { Check, CheckCircle2, ChevronRight, Copy, LoaderCircle, Plug, RefreshCw, Search, Unplug } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

/**
 * The same sheet drives Settings → Connectors AND an agent's Connectors
 * page. `mode` controls which route group it talks to; the destructive
 * action is always labelled "Disconnect" so customers don't have to think
 * about the difference between deleting a row and removing a pivot.
 *
 * Settings mode hits /settings/connectors/* and operates on generic
 * connectors only — the destructive action deletes the definition.
 *
 * Agent mode hits /my/agents/{agent}/sources/connectors/* and operates on
 * what the agent has wired up. `server.scope` tells the destructive
 * callback whether to hard-delete the row (agent-specific) or just drop
 * the pivot (attached generic) — both are surfaced as "Disconnect".
 */
type Mode = 'settings' | 'agent';

type Server = AgentMcpServer & {
    scope?: 'agent' | 'generic';
    attachment_enabled?: boolean | null;
};

interface SheetRow {
    connector: Connector | null;
    server: Server | null;
}

interface Props {
    open: boolean;
    mode: Mode;
    row: SheetRow | null;
    agentId?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    updated: [server: Server];
    removed: [slug: string];
    'connect-token': [connector: Connector];
    'connect-oauth': [connector: Connector];
    'agents-changed': [];
}>();

const activeTab = ref<'overview' | 'tools' | 'agents'>('overview');
const agentMgmt = useAgentAttachments('connector');
const agentsLoaded = ref(false);
const tools = ref<McpTool[]>([]);
const toolsLoading = ref(false);
const refreshing = ref(false);
const expanded = ref<Record<string, boolean>>({});
const toolSearch = ref('');

const filteredTools = computed(() => {
    const q = toolSearch.value.trim().toLowerCase();
    if (!q) return tools.value;
    return tools.value.filter((t) => t.short_name.toLowerCase().includes(q) || (t.description ?? '').toLowerCase().includes(q));
});
const disconnectOpen = ref(false);

const connector = computed(() => props.row?.connector ?? null);
const server = computed(() => props.row?.server ?? null);
const displayName = computed(() => connector.value?.name ?? server.value?.name ?? '');
const description = computed(() => connector.value?.description ?? 'Custom MCP server added directly via URL.');
const isConnected = computed(() => server.value !== null);
const idCopied = ref(false);

async function copyServerId(): Promise<void> {
    if (!server.value) return;
    await navigator.clipboard.writeText(server.value.external_id);
    idCopied.value = true;
    setTimeout(() => (idCopied.value = false), 2000);
}

const isAgentMode = computed(() => props.mode === 'agent');
const isAgentScope = computed(() => server.value?.scope === 'agent');
const brandColor = computed(() => connector.value?.brandColor ?? '6B7280');
const logo = computed(() => {
    if (!connector.value) return null;
    return connector.value.logoUrl ?? `https://cdn.simpleicons.org/${connector.value.icon}/${connector.value.brandColor}`;
});

// Status label depends on mode + scope so the user always knows what they're
// looking at when the same sheet renders for different contexts. Customers
// don't care about agent-specific vs generic — both show as "Connected".
const statusLabel = computed(() => {
    if (!isConnected.value) return 'Not connected';
    return 'Connected';
});

// One verb everywhere. The semantics still differ underneath — agent-
// specific rows are hard-deleted while attached generics just lose their
// pivot row — but customers only need to read the description to know
// which one applies. The label is inlined in the template; the
// description below switches text based on scope.
const destructiveDescription = computed(() => {
    if (!isAgentMode.value) {
        return 'The chat will no longer be able to call tools from this connector. You can reconnect anytime.';
    }
    return isAgentScope.value
        ? 'This connector lives only inside this agent and cannot be recovered.'
        : 'This agent will lose access. The connector definition stays available in Settings → Connectors.';
});

// `currentEnabled` reads the right flag for the current scope so the same
// "Enabled" toggle controls server.is_enabled (settings + agent-specific) or
// the pivot's attachment_enabled (attached generic).
const currentEnabled = computed<boolean>(() => {
    if (!server.value) return false;
    if (isAgentMode.value && server.value.scope === 'generic') {
        return server.value.attachment_enabled === true;
    }
    return server.value.is_enabled === true;
});

watch(
    () => [props.open, props.row?.server?.slug, activeTab.value],
    async () => {
        if (!props.open || !server.value || activeTab.value !== 'tools') return;
        if (tools.value.length > 0 && !toolsLoading.value) return;
        await loadTools();
    },
);

watch(
    () => props.row?.server?.slug,
    () => {
        tools.value = [];
        expanded.value = {};
        activeTab.value = 'overview';
        toolSearch.value = '';
        agentMgmt.reset();
        agentsLoaded.value = false;
    },
);

// Lazy-load the agent lists the first time the user opens Agents/Attach.
watch(
    () => [props.open, props.row?.server?.slug, activeTab.value],
    async () => {
        if (!props.open || !server.value) return;
        if (activeTab.value !== 'agents') return;
        if (agentsLoaded.value) return;
        agentsLoaded.value = true;
        await agentMgmt.load(server.value.slug);
    },
);

async function onToggleAttachment(agentId: string): Promise<void> {
    if (!server.value) return;
    await agentMgmt.toggleAttachment(server.value.slug, agentId);
    emit('agents-changed');
}

// Route builders — single switch point for the two modes.
function toolsRoute(slug: string): string {
    return isAgentMode.value
        ? route('agents.sources.connectors.tools', { agent: props.agentId!, slug })
        : route('settings.connectors.tools', { slug });
}

function refreshRoute(slug: string): string {
    // Each mode has its own endpoint because the lookup rules differ:
    // settings only finds generics; the agent endpoint resolves
    // agent-specific rows AND pivot-attached generics for this agent.
    return isAgentMode.value
        ? route('agents.sources.connectors.refresh', { agent: props.agentId!, slug })
        : route('settings.connectors.refresh', { slug });
}

function enableRoute(slug: string): string {
    return isAgentMode.value
        ? route('agents.sources.connectors.enable', { agent: props.agentId!, slug })
        : route('settings.connectors.enable', { slug });
}

function destructiveRoute(slug: string): string {
    if (!isAgentMode.value) {
        return route('settings.connectors.destroy', { slug });
    }
    // Agent mode: deletion semantics differ by scope.
    return isAgentScope.value
        ? route('agents.sources.connectors.delete', { agent: props.agentId!, slug })
        : route('agents.sources.connectors.disconnect', { agent: props.agentId!, slug });
}

async function loadTools(): Promise<void> {
    if (!server.value) return;
    toolsLoading.value = true;
    try {
        const response = await axios.get(toolsRoute(server.value.slug));
        tools.value = response.data.tools;
    } catch (err) {
        console.error(err);
        toast.error('Failed to load tools');
    } finally {
        toolsLoading.value = false;
    }
}

async function refreshTools(): Promise<void> {
    // The server is the source of truth — custom (non-catalog) connectors
    // don't have a `connector` entry but still need refreshing.
    if (!server.value) return;
    refreshing.value = true;
    try {
        const response = await axios.post(refreshRoute(server.value.slug));
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

async function toggleToolEnabled(tool: McpTool): Promise<void> {
    if (isAgentMode.value || !server.value || tool.id === undefined) return;
    const previous = tool.is_enabled;
    tool.is_enabled = !previous;
    try {
        const response = await axios.post(route('settings.connectors.tools.toggle', { slug: server.value.slug, tool: tool.id }));
        tool.is_enabled = response.data.is_enabled;
    } catch (e: unknown) {
        tool.is_enabled = previous;
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? 'Failed to update tool');
    }
}

async function toggleEnabled(): Promise<void> {
    if (!server.value) return;
    try {
        const response = await axios.post(enableRoute(server.value.slug));
        emit('updated', response.data.data);
    } catch (err) {
        console.error(err);
        toast.error('Failed to toggle');
    }
}

function askDisconnect(): void {
    // Sheet + AlertDialog at the same z-index causes overlap glitches; close
    // the sheet first, then open the confirm a beat later.
    emit('update:open', false);
    setTimeout(() => {
        disconnectOpen.value = true;
    }, 150);
}

async function confirmDisconnect(): Promise<void> {
    if (!server.value) return;
    const slug = server.value.slug;
    try {
        await axios.delete(destructiveRoute(slug));
        emit('removed', slug);
        emit('update:open', false);
        toast.success(`Disconnected ${displayName.value}`);
    } catch (err) {
        console.error(err);
        toast.error('Failed');
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

const connectButtonLabel = computed(() => {
    if (!connector.value) return '';
    return connector.value.authStrategy === 'oauth' ? `Sign in with ${connector.value.name}` : `Connect ${connector.value.name}`;
});
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
                            <span class="text-muted-foreground">{{ statusLabel }}</span>
                        </div>
                    </div>
                </div>
                <SheetDescription class="text-sm leading-relaxed">{{ description }}</SheetDescription>
            </SheetHeader>

            <Tabs v-model="activeTab" class="px-6 pt-5">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="tools" :disabled="!isConnected">Tools</TabsTrigger>
                    <TabsTrigger value="agents" :disabled="!isConnected">Agents</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-6 pt-6">
                    <div v-if="!isConnected && connector">
                        <p v-if="connector.comingSoon" class="text-muted-foreground text-sm">This connector is coming soon.</p>
                        <Button v-else class="w-full" @click="startConnect">
                            <Plug class="mr-2 h-4 w-4" />
                            {{ connectButtonLabel }}
                        </Button>
                    </div>

                    <dl v-if="isConnected" class="divide-border divide-y text-sm">
                        <div v-if="server" class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                            <dt class="text-muted-foreground col-span-1">Server ID</dt>
                            <dd class="col-span-2 flex min-w-0 items-center gap-1.5">
                                <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="server?.external_id">{{ server?.external_id }}</code>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 shrink-0 px-1.5"
                                    title="Copy server ID — pass as mcp_id when attaching via the API"
                                    @click="copyServerId"
                                >
                                    <Check v-if="idCopied" class="size-3.5 text-green-600" />
                                    <Copy v-else class="size-3.5" />
                                </Button>
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                            <dt class="text-muted-foreground col-span-1">Endpoint</dt>
                            <dd class="col-span-2 font-mono text-xs leading-relaxed break-all">{{ server?.url }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">Auth</dt>
                            <dd class="col-span-2 capitalize">{{ server?.auth_type }}</dd>
                        </div>
                        <div v-if="isAgentMode" class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">Scope</dt>
                            <dd class="col-span-2">{{ isAgentScope ? 'Agent-specific' : 'Generic (shared)' }}</dd>
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
                            <span class="text-sm font-medium">{{ isAgentMode ? 'Enabled here' : 'Enabled' }}</span>
                            <Button size="sm" :variant="currentEnabled ? 'default' : 'outline'" class="min-w-16" @click="toggleEnabled">
                                {{ currentEnabled ? 'On' : 'Off' }}
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
                        <div class="relative mb-3">
                            <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input v-model="toolSearch" placeholder="Search tools" class="pl-9" />
                        </div>

                        <div class="text-muted-foreground mb-3 flex items-center gap-2 text-xs">
                            <span>Interactive tools</span>
                            <Badge variant="secondary"
                                >{{ filteredTools.length
                                }}<template v-if="filteredTools.length !== tools.length"> / {{ tools.length }}</template></Badge
                            >
                        </div>

                        <div v-if="filteredTools.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                            No tools match your search.
                        </div>

                        <div v-else class="divide-border divide-y">
                            <div v-for="tool in filteredTools" :key="tool.name" class="py-4 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium" :class="{ 'text-muted-foreground line-through': !tool.is_enabled }">
                                            {{ tool.short_name }}
                                        </p>
                                        <p class="text-muted-foreground mt-1.5 text-sm leading-relaxed">{{ tool.description }}</p>
                                    </div>
                                    <!-- Per-tool enable toggle. Flips `mcp_tools.is_enabled`,
                                         which is scoped to the server — every agent attached
                                         to it sees the change immediately. Hidden when the
                                         server has only one tool, since toggling it is
                                         indistinguishable from disabling the whole connector. -->
                                    <Switch
                                        v-if="!isAgentMode && tool.id !== undefined && server && tools.length > 1"
                                        :model-value="tool.is_enabled"
                                        :title="
                                            tool.is_enabled ? 'Tool active — disable for all agents using this connector' : 'Tool disabled — re-enable'
                                        "
                                        @update:model-value="toggleToolEnabled(tool)"
                                    />
                                </div>
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

                <TabsContent value="agents" class="pt-6">
                    <p class="text-muted-foreground mb-3 text-sm">Toggle which of your agents can use this connector.</p>

                    <div v-if="agentMgmt.loading.value" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="size-4 animate-spin" />
                        Loading agents
                    </div>

                    <div v-else-if="agentMgmt.all.value.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                        You have no agents yet.
                    </div>

                    <ul v-else class="divide-border divide-y">
                        <li
                            v-for="agent in agentMgmt.all.value"
                            :key="agent.id"
                            class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="truncate text-sm font-medium">{{ agent.name }}</span>
                            <Switch
                                :model-value="agent.attached"
                                :disabled="agentMgmt.busyId.value === agent.id"
                                @update:model-value="() => onToggleAttachment(agent.id)"
                            />
                        </li>
                    </ul>
                </TabsContent>
            </Tabs>

            <div class="h-6"></div>
        </SheetContent>
    </Sheet>

    <DeleteConfirmationDialog
        :open="disconnectOpen"
        :title="`Disconnect ${displayName}?`"
        :description="destructiveDescription"
        confirm-text="Disconnect"
        cancel-text="Keep"
        @cancel="disconnectOpen = false"
        @confirm="confirmDisconnect"
    />
</template>
