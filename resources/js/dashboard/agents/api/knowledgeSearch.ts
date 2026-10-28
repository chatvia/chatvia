/**
 * Whether an agent searches its knowledge: the switch on the Knowledge
 * Sources page, shown while the admin lets agents turn search off.
 */
import settings from '@/routes/agents/settings';
import { post } from '@/shared/api/http';

export type KnowledgeSearchState = Domain.Knowledge.Retrieval.Data.KnowledgeSearchStateData;

/** Turns knowledge search on or off for the agent. */
export function setKnowledgeSearch(agentId: string, enabled: boolean): Promise<void> {
    return post(settings.update.url({ agent: agentId, group: 'knowledge' }), { search: enabled });
}
