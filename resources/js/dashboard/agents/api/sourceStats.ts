/**
 * How many knowledge sources of each kind (files, websites, texts, …) an agent
 * has. Shown as counts in the agent sidebar and on the Knowledge Sources page.
 */
import { stats } from '@/routes/agents/sources';
import { get } from '@/shared/api/http';

export type SourceStats = Domain.Agent.Data.Resources.Sources.AgentSourcesStatsData;

export function fetchSourceStats(agentId: string): Promise<SourceStats> {
    return get<SourceStats>(stats.url(agentId));
}
