/**
 * How many knowledge sources of each kind (files, websites, texts, …) an agent
 * has. Shown as counts in the agent sidebar and on the Knowledge Sources page.
 */
import { get } from './http';

export type SourceStats = Domain.Agent.Data.Resources.Sources.AgentSourcesStatsData;

export function fetchSourceStats(agentId: string): Promise<SourceStats> {
    return get<SourceStats>(route('agents.sources.stats', { agent: agentId }));
}
