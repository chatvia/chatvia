/**
 * Tools tab state for the connector detail sheet: the server's tool catalog
 * (loaded lazily the first time the tab is shown for a server), the search
 * box, the read-only / write sections, catalog refresh, and optimistic
 * per-tool and per-section permission changes that are reverted when the
 * server rejects them. The sheet owns this state, not the tab, because the
 * tab's DOM is unmounted whenever the sheet closes.
 */
import { fetchConnectorTools, refreshConnector, setConnectorScopePermission, setConnectorToolPermission } from '@/api/connectors';
import { errorMessage } from '@/api/http';
import type { AgentMcpServer, McpTool, McpToolPermission, McpToolScope } from '@/types/connector';
import { Ban, Check, Hand } from 'lucide-vue-next';
import { computed, ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export interface ToolPermissionOption {
    value: McpToolPermission;
    label: string;
    icon: typeof Check;
}

export interface ToolSection {
    key: McpToolScope;
    title: string;
    items: McpTool[];
}

/**
 * The tri-state permission choices shown per tool and per section: check =
 * always allow, hand = needs approval, ban = blocked (excluded from the
 * catalog the agent sees).
 */
export function useToolPermissionOptions() {
    const { t } = useI18n();

    return computed<ToolPermissionOption[]>(() => [
        { value: 'allow', label: t('shared.connector_sheet.permission_always_allow'), icon: Check },
        { value: 'approval', label: t('shared.connector_sheet.permission_needs_approval'), icon: Hand },
        { value: 'blocked', label: t('shared.connector_sheet.permission_blocked'), icon: Ban },
    ]);
}

export interface ConnectorToolsOptions {
    /** Whether the sheet is open. */
    open: Readonly<Ref<boolean>>;
    /** Whether the Tools tab is the selected one. */
    active: Readonly<Ref<boolean>>;
    server: Readonly<Ref<AgentMcpServer | null>>;
    /** Connector name used in the refresh toast. */
    displayName: Readonly<Ref<string>>;
    /** Called with the server row the refresh endpoint returns. */
    onUpdated: (server: AgentMcpServer) => void;
}

export function useConnectorTools({ open, active, server, displayName, onUpdated }: ConnectorToolsOptions) {
    const { t } = useI18n();
    const permissionOptions = useToolPermissionOptions();

    const tools = ref<McpTool[]>([]);
    const loading = ref(false);
    const refreshing = ref(false);
    const expanded = ref<Record<string, boolean>>({});
    const search = ref('');

    // Both sections start collapsed — the user opens whichever one they want
    // to tune.
    const sectionsOpen = ref<Record<McpToolScope, boolean>>({ read: false, write: false });

    const filteredTools = computed(() => {
        const q = search.value.trim().toLowerCase();
        if (!q) return tools.value;
        return tools.value.filter((tool) => tool.short_name.toLowerCase().includes(q) || (tool.description ?? '').toLowerCase().includes(q));
    });

    // Read-only tools on top, write/destructive below. `scope` comes from the
    // MCP server's `readOnlyHint` annotation at catalog time.
    const sections = computed<ToolSection[]>(() => [
        { key: 'read', title: t('shared.connector_sheet.section_read_only'), items: filteredTools.value.filter((tool) => tool.scope === 'read') },
        {
            key: 'write',
            title: t('shared.connector_sheet.section_write_delete'),
            items: filteredTools.value.filter((tool) => tool.scope === 'write'),
        },
    ]);

    async function load(): Promise<void> {
        if (!server.value) return;
        loading.value = true;
        try {
            tools.value = (await fetchConnectorTools(server.value.slug)).tools;
        } catch (err) {
            console.error(err);
            toast.error(t('shared.connector_sheet.toast_load_tools_failed'));
        } finally {
            loading.value = false;
        }
    }

    async function refresh(): Promise<void> {
        // The server is the source of truth — custom (non-catalog) connectors
        // don't have a `connector` entry but still need refreshing.
        if (!server.value) return;
        refreshing.value = true;
        try {
            const result = await refreshConnector(server.value.slug);
            onUpdated(result.data);
            await load();
            toast.success(t('shared.connector_sheet.toast_refresh_success', { name: displayName.value, count: result.tools_count }));
        } catch (err) {
            toast.error(errorMessage(err, t('shared.connector_sheet.toast_refresh_failed')));
        } finally {
            refreshing.value = false;
        }
    }

    /** Optimistic per-tool permission change, reverted on failure. */
    async function setToolPermission(tool: McpTool, permission: McpToolPermission): Promise<void> {
        if (!server.value || tool.id === undefined) return;
        if (tool.permission === permission) return;
        const previous = tool.permission;
        tool.permission = permission;
        try {
            tool.permission = (await setConnectorToolPermission(server.value.slug, tool.id, permission)).permission;
        } catch (err) {
            tool.permission = previous;
            toast.error(errorMessage(err, t('shared.connector_sheet.toast_permission_failed')));
        }
    }

    /**
     * Apply one permission to every tool of a scope in a single request,
     * updating all rows optimistically and reverting them together on failure.
     */
    async function applySectionPermission(scope: McpToolScope, permission: McpToolPermission): Promise<void> {
        if (!server.value) return;
        const items = tools.value.filter((tool) => tool.scope === scope && tool.id !== undefined);
        if (items.length === 0) return;
        const previous = new Map(items.map((tool) => [tool.id, tool.permission] as const));
        items.forEach((tool) => (tool.permission = permission));
        try {
            await setConnectorScopePermission(server.value.slug, scope, permission);
            const label = permissionOptions.value.find((option) => option.value === permission)?.label ?? '';
            toast.success(t('shared.connector_sheet.toast_bulk_permission', { count: items.length, permission: label }, items.length));
        } catch (err) {
            items.forEach((tool) => (tool.permission = previous.get(tool.id) ?? tool.permission));
            toast.error(errorMessage(err, t('shared.connector_sheet.toast_permission_failed')));
        }
    }

    function toggleDetails(toolName: string): void {
        expanded.value[toolName] = !expanded.value[toolName];
    }

    function setSectionOpen(scope: McpToolScope, value: boolean): void {
        sectionsOpen.value[scope] = value;
    }

    // Load the catalog the first time the tab is shown for a server.
    watch(
        () => [open.value, server.value?.slug, active.value],
        async () => {
            if (!open.value || !server.value || !active.value) return;
            if (tools.value.length > 0 && !loading.value) return;
            await load();
        },
    );

    // Forget the previous server's tools when the sheet switches to another one.
    watch(
        () => server.value?.slug,
        () => {
            tools.value = [];
            expanded.value = {};
            search.value = '';
        },
    );

    return {
        tools,
        loading,
        refreshing,
        expanded,
        search,
        sectionsOpen,
        filteredTools,
        sections,
        refresh,
        setToolPermission,
        applySectionPermission,
        toggleDetails,
        setSectionOpen,
    };
}
