/**
 * Frontend shapes of the connector catalog and the workspace's MCP servers.
 *
 * Hand-written mirrors of the PHP Data classes (narrower unions than the
 * generated `Domain.Integration.*` types where the UI branches on a value);
 * the enum-like unions are taken from the generated types so they stay in
 * sync with the server.
 */

/**
 * Mirrors Domain\Integration\Connector\Data\Catalog\ConnectorData on the server.
 */
export interface Connector {
    slug: string;
    name: string;
    description: string;
    category: string;
    icon: string;
    brandColor: string;
    authStrategy: Domain.Integration.Connector.Enums.ConnectorAuthStrategy;
    mcpUrl: string;
    provider: 'chatvia' | 'featured';
    logoUrl: string | null;
    authType: string | null;
    tokenLabel: string | null;
    tokenHelpUrl: string | null;
    tokenPattern: string | null;
    tokenPlaceholder: string | null;
    // When true, no fixed mcpUrl exists — the connect modal also asks the user
    // for their own MCP server URL (token connectors without a hosted server).
    requiresUrl: boolean;
    comingSoon: boolean;
    requiresClientId: boolean;
    // Surfaces the connector's tools are allowed on (ConversationSource values,
    // e.g. ['hosted_chat']). Null means every surface.
    surfaces: string[] | null;
}

/**
 * Mirrors Domain\Integration\Mcp\Data\Resources\AgentMcpServerData on the server.
 */
export interface AgentMcpServer {
    external_id: string;
    name: string;
    slug: string;
    connector_slug: string | null;
    description: string | null;
    url: string;
    transport: 'streamable_http' | 'http_sse';
    auth_type: 'none' | 'bearer' | 'header';
    timeout_ms: number;
    is_enabled: boolean;
    catalog_tools_count: number;
    catalog_cached_at: string | null;
    last_invoked_at: string | null;
    last_error: string | null;
    invocation_count: number;
    agents_count: number;
}

/**
 * The row shape Settings → Connectors returns. `connector` is null for
 * custom rows; `server` is null for catalog rows the user hasn't connected
 * yet.
 */
export interface ConnectorRow {
    kind: Domain.Integration.Connector.Enums.ConnectorRowKind;
    connector: Connector | null;
    server: AgentMcpServer | null;
}

/** Mirrors Domain\Integration\Connector\Data\Account\AccountConnectorsResourceData. */
export interface ConnectorListResponse {
    featured: ConnectorRow[];
    custom: ConnectorRow[];
}

export type McpToolPermission = Domain.Integration.Mcp.Enums.McpToolPermission;
export type McpToolScope = Domain.Integration.Mcp.Enums.McpToolScope;

/** Mirrors Domain\Integration\Connector\Data\Catalog\ConnectorToolData. */
export interface McpTool {
    id?: number;
    name: string;
    short_name: string;
    description: string;
    parameters: Record<string, unknown>;
    scope: McpToolScope;
    permission: McpToolPermission;
}

/** Mirrors Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData. */
export interface ConnectorToolsResponse {
    tools: McpTool[];
    cached_at: string | null;
}
