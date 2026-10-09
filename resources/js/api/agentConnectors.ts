/**
 * The agent's Connectors page: which of the workspace's connectors (catalog
 * and custom MCP servers) this agent may call, attaching and detaching them,
 * and the "always on" flag that ships a connector's tools on every turn.
 * Connectors themselves are connected in Workspace → Integrations.
 */
import { isAxiosError } from 'axios';
import { del, errorMessage, get, post, put } from './http';

export type AgentConnectors = Domain.Integration.Connector.Data.Agent.AgentConnectorsResourceData;
export type FeaturedConnectorRow = Domain.Integration.Connector.Data.Agent.AgentConnectorRowData;
export type CustomConnectorRow = Domain.Integration.Connector.Data.Custom.AgentCustomConnectorRowData;

/** Catalog rows (with their attach state) and the workspace's custom servers. */
export function fetchAgentConnectors(agentId: string): Promise<AgentConnectors> {
    return get<AgentConnectors>(route('agents.sources.connectors.data', { agent: agentId }));
}

/** Let this agent use one of the workspace's connectors. */
export async function attachConnector(agentId: string, slug: string): Promise<void> {
    await post(route('agents.sources.connectors.attach', { agent: agentId, slug }));
}

/** Stop this agent from using a connector; the workspace keeps the connection. */
export async function detachConnector(agentId: string, slug: string): Promise<void> {
    await del(route('agents.sources.connectors.detach', { agent: agentId, slug }));
}

/** Ship (or stop shipping) an attached connector's tools on every turn. */
export async function setConnectorAlwaysAvailable(agentId: string, slug: string, alwaysAvailable: boolean): Promise<void> {
    await put(route('agents.sources.connectors.always_available', { agent: agentId, slug }), { always_available: alwaysAvailable });
}

/**
 * The server's message when "always on" is refused with a 422 (the per-agent
 * cap on always-shipped tools), else the fallback. Other failures never show
 * the server text.
 */
export function alwaysAvailableErrorMessage(error: unknown, fallback: string): string {
    if (!isAxiosError(error) || error.response?.status !== 422) {
        return fallback;
    }

    return errorMessage(error, '') || fallback;
}
