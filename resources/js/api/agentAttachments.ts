/**
 * Agent attachment API shared by the Functions, Connectors and Chatters sheets:
 * list every accessible agent split into attached/available, and attach or
 * detach one. The three resources expose identical endpoints; only the
 * route group and the id parameter differ.
 */
import connectorAgents from '@/routes/integrations/connectors/agents';
import functionAgents from '@/routes/integrations/functions/agents';
import chatterAgents from '@/routes/people/chatters/agents';
import { del, get, post } from './http';

export type AgentAttachmentKind = 'function' | 'connector' | 'chatter';

/** The agents a resource is attached to, and the ones it could be attached to. */
export type AgentAttachments = Domain.Membership.Agent.Data.Resources.AgentMembershipAssignmentData;

/** Functions and connectors live under Workspace → Integrations; chatters under People. */
const attachmentRoutes = {
    function: {
        index: (id: string) => functionAgents.index.url({ function: id }),
        attach: (id: string, agent: string) => functionAgents.attach.url({ function: id, agent }),
        detach: (id: string, agent: string) => functionAgents.detach.url({ function: id, agent }),
    },
    connector: {
        index: (slug: string) => connectorAgents.index.url({ slug }),
        attach: (slug: string, agent: string) => connectorAgents.attach.url({ slug, agent }),
        detach: (slug: string, agent: string) => connectorAgents.detach.url({ slug, agent }),
    },
    chatter: {
        index: (chatter: string) => chatterAgents.index.url({ chatter }),
        attach: (chatter: string, agent: string) => chatterAgents.attach.url({ chatter, agent }),
        detach: (chatter: string, agent: string) => chatterAgents.detach.url({ chatter, agent }),
    },
} satisfies Record<AgentAttachmentKind, unknown>;

/** Every accessible agent, split into attached and available. */
export function fetchAgentAttachments(kind: AgentAttachmentKind, resourceId: string): Promise<AgentAttachments> {
    return get<AgentAttachments>(attachmentRoutes[kind].index(resourceId));
}

/** Attach the resource to an agent; answers with the updated lists. */
export function attachAgent(kind: AgentAttachmentKind, resourceId: string, agentId: string): Promise<AgentAttachments> {
    return post<AgentAttachments>(attachmentRoutes[kind].attach(resourceId, agentId));
}

/** Detach the resource from an agent; answers with the updated lists. */
export function detachAgent(kind: AgentAttachmentKind, resourceId: string, agentId: string): Promise<AgentAttachments> {
    return del<AgentAttachments>(attachmentRoutes[kind].detach(resourceId, agentId));
}
