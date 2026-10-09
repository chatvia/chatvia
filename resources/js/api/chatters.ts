/**
 * Chatters API: the agent → Chatters tab list (every workspace chatter with an
 * access flag for that agent), the workspace People → Chatters list, and the
 * detail sheet's access-controls save. Invites, grants, revokes, password
 * resets and deletes are Inertia visits, so they do not live here.
 */
import { get, patch } from './http';

export type AgentChatterRow = Domain.Membership.Agent.Data.Resources.AgentChatterRowData;
export type WorkspaceChatterRow = App.Identity.Chatter.Data.WorkspaceChatterRowData;

/** Body of the detail sheet's save: the monthly message limit and the deactivation switch. */
export interface UpdateChatterAccessPayload {
    messages_limit: number | null;
    deactivated: boolean;
    deactivation_reason: string | null;
}

/** Both list endpoints wrap their rows as `{ data: rows }`. */
interface RowsEnvelope<T> {
    data?: T[] | null;
}

type MaybeEnveloped<T> = T | { data: T };

function isEnveloped<T>(body: MaybeEnveloped<T>): body is { data: T } {
    return typeof body === 'object' && body !== null && 'data' in body && body.data != null;
}

/** Every chatter in the agent's workspace with a `has_access` flag for this agent, granted first. */
export async function listAgentChatters(agentId: string): Promise<AgentChatterRow[]> {
    const body = await get<RowsEnvelope<AgentChatterRow>>(route('agents.chatters.index', { agent: agentId }));

    return body.data ?? [];
}

/** Every chatter in the signed-in user's workspace, with agent count and message usage. */
export async function listWorkspaceChatters(): Promise<WorkspaceChatterRow[]> {
    const body = await get<RowsEnvelope<WorkspaceChatterRow>>(route('people.chatters.index'));

    return body.data ?? [];
}

/** Saves a chatter's message limit and deactivation state and returns the refreshed row. */
export async function updateChatterAccess(chatterId: string, payload: UpdateChatterAccessPayload): Promise<WorkspaceChatterRow> {
    const body = await patch<MaybeEnveloped<WorkspaceChatterRow>, UpdateChatterAccessPayload>(
        route('people.chatters.update', { chatter: chatterId }),
        payload,
    );

    // Tolerate either a bare row or a `{ data: row }` envelope.
    return isEnveloped(body) ? body.data : body;
}
