/**
 * Mirrors App\Data\Connectors\ConnectorData on the server.
 */
export interface Connector {
    slug: string;
    name: string;
    description: string;
    category: string;
    icon: string;
    brandColor: string;
    authStrategy: 'token' | 'oauth' | 'none';
    mcpUrl: string;
    provider: 'chatvia' | 'featured';
    logoUrl: string | null;
    authType: string | null;
    tokenLabel: string | null;
    tokenHelpUrl: string | null;
    tokenPattern: string | null;
    tokenPlaceholder: string | null;
    comingSoon: boolean;
}

/**
 * Mirrors App\Data\Resources\Agent\AgentMcpServerData on the server.
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
    kind: 'featured' | 'custom';
    connector: Connector | null;
    server: AgentMcpServer | null;
}

export interface ConnectorListResponse {
    featured: ConnectorRow[];
    custom: ConnectorRow[];
}

export type McpToolPermission = 'allow' | 'approval' | 'blocked';
export type McpToolScope = 'read' | 'write';

export interface McpTool {
    id?: number;
    name: string;
    short_name: string;
    description: string;
    parameters: Record<string, unknown>;
    scope: McpToolScope;
    permission: McpToolPermission;
}

export interface ConnectorToolsResponse {
    tools: McpTool[];
    cached_at: string | null;
}
