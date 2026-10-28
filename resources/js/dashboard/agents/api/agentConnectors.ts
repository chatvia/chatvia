/**
 * The agent's Connectors page: which of the workspace's connectors (catalog
 * and custom MCP servers) this agent may call, attaching and detaching them,
 * and the "always on" flag that ships a connector's tools on every turn.
 * Connectors themselves are connected in Workspace → Integrations.
 */
import connectors from '@/routes/agents/sources/connectors';
import { del, get, post, put } from '@/shared/api/http';

export type AgentConnectors = Domain.Integration.Connector.Data.Agent.AgentConnectorsResourceData;
export type FeaturedConnectorRow = Domain.Integration.Connector.Data.Agent.AgentConnectorRowData;
export type CustomConnectorRow = Domain.Integration.Connector.Data.Custom.AgentCustomConnectorRowData;

/** Catalog rows (with their attach state) and the workspace's custom servers. */
export function fetchAgentConnectors(agentId: string): Promise<AgentConnectors> {
    return get<AgentConnectors>(connectors.data.url(agentId));
}

/** Let this agent use one of the workspace's connectors. */
export async function attachConnector(agentId: string, slug: string): Promise<void> {
    await post(connectors.attach.url({ agent: agentId, slug }));
}

/** Stop this agent from using a connector; the workspace keeps the connection. */
export async function detachConnector(agentId: string, slug: string): Promise<void> {
    await del(connectors.detach.url({ agent: agentId, slug }));
}

/** Ship (or stop shipping) an attached connector's tools on every turn. */
export async function setConnectorAlwaysAvailable(agentId: string, slug: string, alwaysAvailable: boolean): Promise<void> {
    await put(connectors.alwaysAvailable.url({ agent: agentId, slug }), { always_available: alwaysAvailable });
}
