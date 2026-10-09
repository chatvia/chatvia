/**
 * Agent attachment API shared by the Functions, Connectors and Chatters sheets:
 * list every accessible agent split into attached/available, and attach or
 * detach one. The three resources expose identical endpoints; only the
 * route-name prefix and the id parameter differ.
 */
import { del, get, post } from './http';

export type AgentAttachmentKind = 'function' | 'connector' | 'chatter';

/** The agents a resource is attached to, and the ones it could be attached to. */
export type AgentAttachments = Domain.Membership.Agent.Data.Resources.AgentMembershipAssignmentData;

type AttachmentAction = 'index' | 'attach' | 'detach';

/** Functions and connectors live under Workspace → Integrations; chatters under People. */
function attachmentUrl(kind: AgentAttachmentKind, action: AttachmentAction, resourceId: string, agentId?: string): string {
    const prefix = kind === 'function' ? 'integrations.functions' : kind === 'connector' ? 'integrations.connectors' : 'people.chatters';
    const idKey = kind === 'function' ? 'external_id' : kind === 'connector' ? 'slug' : 'chatter';
    const params: Record<string, string | undefined> = { [idKey]: resourceId, agent: agentId };

    return route(`${prefix}.agents.${action}`, params);
}

/** Every accessible agent, split into attached and available. */
export function fetchAgentAttachments(kind: AgentAttachmentKind, resourceId: string): Promise<AgentAttachments> {
    return get<AgentAttachments>(attachmentUrl(kind, 'index', resourceId));
}

/** Attach the resource to an agent; answers with the updated lists. */
export function attachAgent(kind: AgentAttachmentKind, resourceId: string, agentId: string): Promise<AgentAttachments> {
    return post<AgentAttachments>(attachmentUrl(kind, 'attach', resourceId, agentId));
}

/** Detach the resource from an agent; answers with the updated lists. */
export function detachAgent(kind: AgentAttachmentKind, resourceId: string, agentId: string): Promise<AgentAttachments> {
    return del<AgentAttachments>(attachmentUrl(kind, 'detach', resourceId, agentId));
}
