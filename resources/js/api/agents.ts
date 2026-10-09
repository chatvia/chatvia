/**
 * The agents of the selected team that the current user may see, with their
 * message totals. Feeds the agents list page.
 */
import { get } from './http';

export type AgentListItem = Domain.Agent.Data.Resources.Account.AgentResourceData;

/** The same URL also renders the Inertia page; axios asks for JSON. */
export function fetchAgents(): Promise<AgentListItem[]> {
    return get<AgentListItem[]>(route('agents.index'));
}
