<script lang="ts" setup>
import { connectorLogoUrl } from '@/lib/connectorLogo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import { useConnectorText } from '@/composables/useConnectorText';
import type { AgentMcpServer, Connector, McpTool, McpToolPermission, McpToolScope } from '@/types/connector';
import axios from 'axios';
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';
import { Ban, Check, CheckCircle2, ChevronDown, ChevronRight, Copy, Hand, LoaderCircle, Plug, RefreshCw, Search, Unplug } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

hljs.registerLanguage('json', json);

/**
 * The same sheet drives Settings → Connectors AND an agent's Connectors
 * page. `mode` controls which route group it talks to; the destructive
 * action is always labelled "Disconnect" so customers don't have to think
 * about the difference between deleting a row and removing a pivot.
 *
 * Settings mode hits /my/workspace/integrations/connectors/* and operates on generic
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

// Section groupings shown in the Tools tab — read-only on top, write/destructive
// below, matching the Anthropic connector layout. Scope is derived from the
// MCP server's `readOnlyHint` annotation at catalog time.
const toolSections = computed<Array<{ key: McpToolScope; title: string; items: McpTool[] }>>(() => [
    { key: 'read', title: t('shared.connector_sheet.section_read_only'), items: filteredTools.value.filter((tool) => tool.scope === 'read') },
    { key: 'write', title: t('shared.connector_sheet.section_write_delete'), items: filteredTools.value.filter((tool) => tool.scope === 'write') },
]);

// Both sections start collapsed — the user opens whichever one they want
// to tune.
const sectionsOpen = ref<Record<McpToolScope, boolean>>({ read: false, write: false });
const disconnectOpen = ref(false);

const connector = computed(() => props.row?.connector ?? null);
const server = computed(() => props.row?.server ?? null);
const displayName = computed(() => (connector.value ? connectorName(connector.value) : (server.value?.name ?? '')));
const description = computed(() => (connector.value ? connectorDescription(connector.value) : t('shared.connector_sheet.default_description')));
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
    return connectorLogoUrl(connector.value);
});

// Status label depends on mode + scope so the user always knows what they're
// looking at when the same sheet renders for different contexts. Customers
// don't care about agent-specific vs generic — both show as "Connected".
const statusLabel = computed(() => {
    if (!isConnected.value) return t('shared.connector_sheet.status_not_connected');
    return t('shared.connector_sheet.status_connected');
});

// One verb everywhere. The semantics still differ underneath — agent-
// specific rows are hard-deleted while attached generics just lose their
// pivot row — but customers only need to read the description to know
// which one applies. The label is inlined in the template; the
// description below switches text based on scope.
const destructiveDescription = computed(() => {
    if (!isAgentMode.value) {
        return t('shared.connector_sheet.disconnect_description_settings');
    }
    return isAgentScope.value
        ? t('shared.connector_sheet.disconnect_description_agent_specific')
        : t('shared.connector_sheet.disconnect_description_agent_generic');
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
        : route('integrations.connectors.tools', { slug });
}

function refreshRoute(slug: string): string {
    // Each mode has its own endpoint because the lookup rules differ:
    // settings only finds generics; the agent endpoint resolves
    // agent-specific rows AND pivot-attached generics for this agent.
    return isAgentMode.value
        ? route('agents.sources.connectors.refresh', { agent: props.agentId!, slug })
        : route('integrations.connectors.refresh', { slug });
}

function enableRoute(slug: string): string {
    return isAgentMode.value
        ? route('agents.sources.connectors.enable', { agent: props.agentId!, slug })
        : route('integrations.connectors.enable', { slug });
}

function destructiveRoute(slug: string): string {
    if (!isAgentMode.value) {
        return route('integrations.connectors.destroy', { slug });
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
        toast.error(t('shared.connector_sheet.toast_load_tools_failed'));
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
        toast.success(t('shared.connector_sheet.toast_refresh_success', { name: displayName.value, count: response.data.tools_count }));
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? t('shared.connector_sheet.toast_refresh_failed'));
    } finally {
        refreshing.value = false;
    }
}

async function toggleEnabled(): Promise<void> {
    if (!server.value) return;
    try {
        const response = await axios.post(enableRoute(server.value.slug));
        emit('updated', response.data.data);
    } catch (err) {
        console.error(err);
        toast.error(t('shared.connector_sheet.toast_toggle_failed'));
    }
}

// Tri-state permission options shown per tool. The icon set mirrors the
// Anthropic connector UX: check = always allow, hand = needs approval,
// ban = blocked (excluded from the catalog).
const permissionOptions = computed<Array<{ value: McpToolPermission; label: string; icon: typeof Check }>>(() => [
    { value: 'allow', label: t('shared.connector_sheet.permission_always_allow'), icon: Check },
    { value: 'approval', label: t('shared.connector_sheet.permission_needs_approval'), icon: Hand },
    { value: 'blocked', label: t('shared.connector_sheet.permission_blocked'), icon: Ban },
]);

// `create_event` → `Create Event`. Matches Laravel's Str::title for snake/
// kebab-cased tool names; non-separated camelCase keeps its existing casing.
function toTitle(name: string): string {
    return name.replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

// Render the tool's JSON schema with highlight.js. The result is injected
// via v-html — hljs escapes the input itself, so this is safe.
function highlightSchema(parameters: unknown): string {
    const source = JSON.stringify(parameters, null, 2) ?? '';
    try {
        return hljs.highlight(source, { language: 'json', ignoreIllegals: true }).value;
    } catch {
        return source.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}

// Optimistic per-tool permission change, reverting on failure.
async function setToolPermission(tool: McpTool, permission: McpToolPermission): Promise<void> {
    if (isAgentMode.value || !server.value || tool.id === undefined) return;
    if (tool.permission === permission) return;
    const previous = tool.permission;
    tool.permission = permission;
    try {
        const response = await axios.put(route('integrations.connectors.tools.permission', { slug: server.value.slug, tool: tool.id }), {
            permission,
        });
        tool.permission = response.data.permission;
    } catch (e: unknown) {
        tool.permission = previous;
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? t('shared.connector_sheet.toast_permission_failed'));
    }
}

// Bulk apply one permission to every tool in a section (settings mode only).
// Operates on the whole scope, optimistically updating all rows and reverting
// them together on failure — one request via the scope-permission endpoint.
async function applySectionPermission(scope: McpToolScope, permission: McpToolPermission): Promise<void> {
    if (isAgentMode.value || !server.value) return;
    const items = tools.value.filter((tool) => tool.scope === scope && tool.id !== undefined);
    if (items.length === 0) return;
    const previous = new Map(items.map((tool) => [tool.id, tool.permission] as const));
    items.forEach((tool) => (tool.permission = permission));
    try {
        await axios.put(route('integrations.connectors.tools.scope-permission', { slug: server.value.slug }), { scope, permission });
        const label = permissionOptions.value.find((option) => option.value === permission)?.label ?? '';
        toast.success(t('shared.connector_sheet.toast_bulk_permission', { count: items.length, permission: label }, items.length));
    } catch (e: unknown) {
        items.forEach((tool) => (tool.permission = previous.get(tool.id) ?? tool.permission));
        const err = e as { response?: { data?: { message?: string } } };
        toast.error(err.response?.data?.message ?? t('shared.connector_sheet.toast_permission_failed'));
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
        toast.success(t('shared.connector_sheet.toast_disconnected', { name: displayName.value }));
    } catch (err) {
        console.error(err);
        toast.error(t('shared.connector_sheet.toast_disconnect_failed'));
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
    return connector.value.authStrategy === 'oauth'
        ? t('shared.connector_sheet.sign_in_with', { name: connector.value.name })
        : t('shared.connector_sheet.connect_name', { name: connector.value.name });
});

const disconnectTitle = computed(() => t('shared.connector_sheet.disconnect_title', { name: displayName.value }));
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
                    <TabsTrigger value="overview">{{ t('shared.connector_sheet.tab_overview') }}</TabsTrigger>
                    <TabsTrigger value="tools" :disabled="!isConnected">{{ t('shared.connector_sheet.tab_tools') }}</TabsTrigger>
                    <TabsTrigger value="agents" :disabled="!isConnected">{{ t('shared.connector_sheet.tab_agents') }}</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-6 pt-6">
                    <div v-if="!isConnected && connector">
                        <p v-if="connector.comingSoon" class="text-muted-foreground text-sm">{{ t('shared.connector_sheet.coming_soon') }}</p>
                        <Button v-else class="w-full" @click="startConnect">
                            <Plug class="me-2 h-4 w-4" />
                            {{ connectButtonLabel }}
                        </Button>
                    </div>

                    <dl v-if="isConnected" class="divide-border divide-y text-sm">
                        <div v-if="server" class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.server_id') }}</dt>
                            <dd class="col-span-2 flex min-w-0 items-center gap-1.5">
                                <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="server?.external_id">{{ server?.external_id }}</code>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 shrink-0 px-1.5"
                                    :title="t('shared.connector_sheet.copy_server_id_title')"
                                    @click="copyServerId"
                                >
                                    <Check v-if="idCopied" class="size-3.5 text-green-600" />
                                    <Copy v-else class="size-3.5" />
                                </Button>
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.endpoint') }}</dt>
                            <dd class="col-span-2 font-mono text-xs leading-relaxed break-all">{{ server?.url }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.auth') }}</dt>
                            <dd class="col-span-2 capitalize">{{ server?.auth_type }}</dd>
                        </div>
                        <div v-if="isAgentMode" class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.scope') }}</dt>
                            <dd class="col-span-2">
                                {{
                                    isAgentScope ? t('shared.connector_sheet.scope_agent_specific') : t('shared.connector_sheet.scope_generic_shared')
                                }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.tools_count') }}</dt>
                            <dd class="col-span-2">{{ server?.catalog_tools_count }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.calls') }}</dt>
                            <dd class="col-span-2">{{ server?.invocation_count ?? 0 }}</dd>
                        </div>
                        <div v-if="server?.last_invoked_at" class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.last_call') }}</dt>
                            <dd class="col-span-2">{{ new Date(server.last_invoked_at).toLocaleString() }}</dd>
                        </div>
                        <div v-if="server?.last_error" class="grid grid-cols-3 gap-4 py-3 last:pb-0">
                            <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.last_error') }}</dt>
                            <dd class="text-destructive col-span-2">{{ server.last_error }}</dd>
                        </div>
                    </dl>

                    <div v-if="isConnected" class="space-y-4 border-t pt-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{
                                isAgentMode ? t('shared.connector_sheet.enabled_here') : t('shared.connector_sheet.enabled')
                            }}</span>
                            <Button size="sm" :variant="currentEnabled ? 'default' : 'outline'" class="min-w-16" @click="toggleEnabled">
                                {{ currentEnabled ? t('shared.connector_sheet.on') : t('shared.connector_sheet.off') }}
                            </Button>
                        </div>
                        <Button variant="destructive" class="w-full" @click="askDisconnect">
                            <Unplug class="me-2 h-4 w-4" />
                            {{ t('shared.connector_sheet.disconnect') }}
                        </Button>
                    </div>
                </TabsContent>

                <TabsContent value="tools" class="space-y-4 pt-6">
                    <Button variant="outline" class="w-full" :disabled="refreshing" @click="refreshTools">
                        <RefreshCw class="me-2 h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                        {{ t('shared.connector_sheet.refresh_tools') }}
                    </Button>

                    <div v-if="toolsLoading" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        {{ t('shared.connector_sheet.loading_tools') }}
                    </div>

                    <div v-else-if="tools.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                        {{ t('shared.connector_sheet.no_tools_advertised') }}
                    </div>

                    <div v-else>
                        <div class="relative mb-4">
                            <Search class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                            <Input v-model="toolSearch" :placeholder="t('shared.connector_sheet.search_tools_placeholder')" class="ps-9" />
                        </div>

                        <div v-if="filteredTools.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                            {{ t('shared.connector_sheet.no_tools_match_search') }}
                        </div>

                        <template v-else>
                            <!-- Read-only and Write/delete sections. `scope` comes from the
                                 MCP server's `readOnlyHint` annotation at catalog time, so
                                 the split mirrors what the server itself declares about
                                 each tool's blast radius. -->
                            <div v-for="section in toolSections" :key="section.key" class="border-b last:border-b-0">
                                <Collapsible v-model:open="sectionsOpen[section.key]">
                                    <div class="flex items-center gap-1">
                                        <CollapsibleTrigger
                                            class="hover:bg-muted/40 flex flex-1 items-center gap-2 rounded-md px-2 py-3 transition-colors"
                                            :disabled="section.items.length === 0"
                                        >
                                            <ChevronRight v-if="!sectionsOpen[section.key]" class="text-muted-foreground h-4 w-4" />
                                            <ChevronDown v-else class="text-muted-foreground h-4 w-4" />
                                            <span class="text-sm font-medium">{{ section.title }}</span>
                                            <Badge variant="secondary" class="text-xs">{{ section.items.length }}</Badge>
                                        </CollapsibleTrigger>

                                        <!-- Section-level bulk permission (settings mode only): applies
                                             one permission to every tool in this scope at once, so the
                                             user doesn't have to set them one by one. -->
                                        <DropdownMenu v-if="!isAgentMode && section.items.length > 0">
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-muted-foreground hover:text-foreground h-7 shrink-0 gap-1 px-2 text-xs"
                                                >
                                                    {{ t('shared.connector_sheet.set_all') }}
                                                    <ChevronDown class="h-3.5 w-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuItem
                                                    v-for="option in permissionOptions"
                                                    :key="option.value"
                                                    class="cursor-pointer"
                                                    @select="applySectionPermission(section.key, option.value)"
                                                >
                                                    <component :is="option.icon" class="me-2 h-4 w-4" />
                                                    {{ option.label }}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                    <CollapsibleContent>
                                        <div v-if="section.items.length === 0" class="text-muted-foreground px-2 py-4 text-center text-xs">
                                            {{ t('shared.connector_sheet.section_none') }}
                                        </div>
                                        <div v-else class="divide-border divide-y ps-6">
                                            <div v-for="tool in section.items" :key="tool.name" class="py-4 first:pt-0 last:pb-0">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p
                                                            class="text-sm font-medium"
                                                            :class="{ 'text-muted-foreground line-through': tool.permission === 'blocked' }"
                                                        >
                                                            {{ toTitle(tool.short_name) }}
                                                        </p>
                                                    </div>
                                                    <!-- Tri-state permission segmented control (settings mode only).
                                                         Allow → tool runs inline; Approval → pauses for user
                                                         confirmation; Blocked → excluded from the catalog. -->
                                                    <TooltipProvider v-if="!isAgentMode && tool.id !== undefined" :delay-duration="200">
                                                        <div class="bg-muted/40 mt-0.5 flex shrink-0 items-center gap-0.5 rounded-md border p-0.5">
                                                            <Tooltip v-for="option in permissionOptions" :key="option.value">
                                                                <TooltipTrigger as-child>
                                                                    <button
                                                                        type="button"
                                                                        class="rounded px-1.5 py-1 transition-colors"
                                                                        :class="
                                                                            tool.permission === option.value
                                                                                ? 'bg-background text-foreground shadow-sm'
                                                                                : 'text-muted-foreground hover:text-foreground'
                                                                        "
                                                                        :aria-label="option.label"
                                                                        :aria-pressed="tool.permission === option.value"
                                                                        @click="setToolPermission(tool, option.value)"
                                                                    >
                                                                        <component :is="option.icon" class="h-3.5 w-3.5" />
                                                                    </button>
                                                                </TooltipTrigger>
                                                                <TooltipContent side="top">{{ option.label }}</TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    </TooltipProvider>
                                                </div>
                                                <button
                                                    class="text-muted-foreground hover:text-foreground mt-3 inline-flex items-center gap-1 text-xs"
                                                    @click="expanded[tool.name] = !expanded[tool.name]"
                                                >
                                                    <ChevronRight
                                                        class="h-3 w-3 transition-transform"
                                                        :class="{ 'rotate-90': expanded[tool.name] }"
                                                    />
                                                    {{ t('shared.connector_sheet.view_details') }}
                                                </button>
                                                <div v-if="expanded[tool.name]" class="mt-3 space-y-3">
                                                    <div>
                                                        <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                                            {{ t('shared.connector_sheet.function') }}
                                                        </p>
                                                        <code class="mt-1 block font-mono text-xs break-all">{{ tool.short_name }}</code>
                                                    </div>
                                                    <div>
                                                        <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                                            {{ t('shared.connector_sheet.description') }}
                                                        </p>
                                                        <p class="mt-1 text-sm leading-relaxed">{{ tool.description }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                                            {{ t('shared.connector_sheet.input_schema') }}
                                                        </p>
                                                        <pre
                                                            class="bg-muted mt-1 overflow-x-auto rounded p-3 font-mono text-xs leading-relaxed"
                                                        ><code class="hljs language-json" v-html="highlightSchema(tool.parameters)"></code></pre>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            </div>
                        </template>
                    </div>
                </TabsContent>

                <TabsContent value="agents" class="pt-6">
                    <p class="text-muted-foreground mb-3 text-sm">{{ t('shared.connector_sheet.agents_toggle_description') }}</p>

                    <div v-if="agentMgmt.loading.value" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="size-4 animate-spin" />
                        {{ t('shared.connector_sheet.loading_agents') }}
                    </div>

                    <div v-else-if="agentMgmt.all.value.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                        {{ t('shared.connector_sheet.no_agents_yet') }}
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
        :title="disconnectTitle"
        :description="destructiveDescription"
        :confirm-text="t('shared.connector_sheet.disconnect')"
        :cancel-text="t('shared.connector_sheet.keep')"
        @cancel="disconnectOpen = false"
        @confirm="confirmDisconnect"
    />
</template>

<style scoped>
/* Highlight.js JSON colors — VS Code light palette by default, "dark+"
   palette in dark mode. Mirrors JsonCodeEditor so highlighted JSON looks
   the same across the app. */
:deep(.hljs) {
    color: var(--foreground);
    background: transparent;
}
:deep(.hljs-attr) {
    color: #0451a5;
}
:deep(.hljs-string) {
    color: #a31515;
}
:deep(.hljs-number),
:deep(.hljs-literal) {
    color: #098658;
}
:deep(.hljs-punctuation) {
    color: var(--muted-foreground);
}

:global(html.dark) :deep(.hljs-attr) {
    color: #9cdcfe;
}
:global(html.dark) :deep(.hljs-string) {
    color: #ce9178;
}
:global(html.dark) :deep(.hljs-number),
:global(html.dark) :deep(.hljs-literal) {
    color: #b5cea8;
}
</style>
