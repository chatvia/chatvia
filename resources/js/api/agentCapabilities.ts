/**
 * The agent's Capabilities page: turning a first-party capability (Interactive
 * Charts, Forms, …) on or off for one agent. The first enable in a workspace
 * connects the capability, so it can take a moment and can fail with a 422
 * whose message is meant for the user.
 */
import { disable, enable } from '@/routes/agents/capabilities';
import { del, post } from './http';

export type AgentCapabilityRow = Domain.Integration.Connector.Data.Agent.AgentCapabilityRowData;

/** Turn a capability on for this agent (connects it for the workspace on first use). */
export async function enableCapability(agentId: string, slug: string): Promise<void> {
    await post(enable.url({ agent: agentId, slug }));
}

/** Turn a capability off for this agent; the workspace keeps it for its other agents. */
export async function disableCapability(agentId: string, slug: string): Promise<void> {
    await del(disable.url({ agent: agentId, slug }));
}
