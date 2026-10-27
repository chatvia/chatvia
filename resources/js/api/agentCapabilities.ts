/**
 * The agent's Capabilities page: turning a first-party capability (Interactive
 * Charts, Forms, …) on or off for one agent. The first enable in a workspace
 * connects the capability, so it can take a moment and can fail with a 422
 * whose message is meant for the user. A capability that works through one
 * of several connectors (Calendar: Calendly or Cal.com) can be told which.
 * Image and Video generation can let chatters pick the model.
 */
import { disable, enable, modelChoice, provider } from '@/routes/agents/capabilities';
import { del, post, put } from './http';

export type AgentCapabilityRow = Domain.Integration.Connector.Data.Agent.AgentCapabilityRowData;

/** Turn a capability on for this agent (connects it for the workspace on first use). */
export async function enableCapability(agentId: string, slug: string): Promise<void> {
    await post(enable.url({ agent: agentId, slug }));
}

/** Turn a capability off for this agent; the workspace keeps it for its other agents. */
export async function disableCapability(agentId: string, slug: string): Promise<void> {
    await del(disable.url({ agent: agentId, slug }));
}

/** Pick the connector a capability works through for this agent (e.g. Calendar through Cal.com). */
export async function chooseCapabilityProvider(agentId: string, slug: string, connectorSlug: string): Promise<void> {
    await put(provider.url({ agent: agentId, slug }), { provider: connectorSlug });
}

/** Let chatters pick the image or video model in the hosted chat, or stop it. */
export async function setCapabilityModelChoice(agentId: string, slug: string, enabled: boolean): Promise<void> {
    await put(modelChoice.url({ agent: agentId, slug }), { enabled });
}
