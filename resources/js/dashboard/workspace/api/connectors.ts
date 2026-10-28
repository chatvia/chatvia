/**
 * Workspace connectors API (Workspace → Integrations → Connectors): the catalog
 * and custom-server list, the connect flows (token, no-auth, custom URL,
 * Salesforce), the enable toggle, disconnect, catalog refresh and per-tool
 * permissions. Every endpoint addresses a connector by its slug.
 */
import connectors from '@/routes/integrations/connectors';
import { del, get, post, put } from '@/shared/api/http';
import type { AgentMcpServer, ConnectorListResponse, ConnectorToolsResponse, McpToolPermission, McpToolScope } from '@/shared/types/connector';

/** A connected (or refreshed) server plus how many tools its catalog has. */
export interface ConnectedServerResponse {
    data: AgentMcpServer;
    tools_count: number;
}

/** A server row after a state change (enable toggle). */
export interface ServerResponse {
    data: AgentMcpServer;
}

/** The OAuth flows answer with the upstream authorize URL to follow. */
export interface RedirectResponse {
    redirect_url: string;
}

/** Credentials for a token connector; `url` only when the connector has no hosted server. */
export interface ConnectTokenPayload {
    token: string;
    url?: string;
}

export interface AddCustomConnectorPayload {
    name: string;
    url: string;
    auth_strategy: Domain.Integration.Connector.Enums.ConnectorAuthStrategy;
    token: string | null;
}

/** The workspace's own Salesforce OAuth client. Optional fields are only sent when filled in. */
export interface SalesforceConnectPayload {
    client_id: string;
    environment: 'production' | 'sandbox';
    server: string;
    client_secret?: string;
    my_domain?: string;
}

/** Mirrors Domain\Integration\Mcp\Data\McpToolsScopePermissionData. */
export interface ScopePermissionResponse {
    scope: McpToolScope;
    permission: McpToolPermission;
    updated: number;
}

/** Featured catalog rows and the workspace's custom servers. */
export function fetchConnectors(): Promise<ConnectorListResponse> {
    return get<ConnectorListResponse>(connectors.data.url());
}

/** Connect a catalog connector: with credentials for token connectors, without a body for no-auth ones. */
export function connectConnector(slug: string, payload?: ConnectTokenPayload): Promise<ConnectedServerResponse> {
    return post<ConnectedServerResponse>(connectors.connect.url(slug), payload);
}

/** Add a custom MCP server by URL. OAuth servers answer with a redirect instead of the new row. */
export function addCustomConnector(payload: AddCustomConnectorPayload): Promise<ConnectedServerResponse | RedirectResponse> {
    return post<ConnectedServerResponse | RedirectResponse>(connectors.custom.add.url(), payload);
}

/** Start the Salesforce OAuth flow with the workspace's own OAuth client. */
export function connectSalesforce(payload: SalesforceConnectPayload): Promise<RedirectResponse> {
    return post<RedirectResponse>(connectors.salesforce.connect.url(), payload);
}

/** Flip a connected server's enabled flag. */
export function toggleConnector(slug: string): Promise<ServerResponse> {
    return post<ServerResponse>(connectors.enable.url(slug));
}

/** Disconnect a server (deletes the workspace's connection). */
export async function disconnectConnector(slug: string): Promise<void> {
    await del(connectors.destroy.url(slug));
}

/** Re-read the server's tool catalog. */
export function refreshConnector(slug: string): Promise<ConnectedServerResponse> {
    return post<ConnectedServerResponse>(connectors.refresh.url(slug));
}

/** The server's cached tool catalog with each tool's permission. */
export function fetchConnectorTools(slug: string): Promise<ConnectorToolsResponse> {
    return get<ConnectorToolsResponse>(connectors.tools.url(slug));
}

/** Set one tool's permission (allow / needs approval / blocked). */
export function setConnectorToolPermission(
    slug: string,
    toolId: number,
    permission: McpToolPermission,
): Promise<Domain.Integration.Mcp.Data.McpToolPermissionData> {
    return put<Domain.Integration.Mcp.Data.McpToolPermissionData>(connectors.tools.permission.url({ slug, tool: toolId }), {
        permission,
    });
}

/** Set the same permission on every tool of one scope (read or write). */
export function setConnectorScopePermission(slug: string, scope: McpToolScope, permission: McpToolPermission): Promise<ScopePermissionResponse> {
    return put<ScopePermissionResponse>(connectors.tools.scopePermission.url(slug), { scope, permission });
}
