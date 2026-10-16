/**
 * The agent's Q&A entries: list them, read one (to edit it) and delete one.
 * Creating and updating an entry are Inertia form visits on the page.
 */
import qa from '@/routes/agents/sources/qa';
import { del, get } from './http';

/** One of the questions an entry answers. `id` is only a stable list key. */
export interface QaQuestion {
    id: string;
    question: string;
}

/**
 * A Q&A entry's status. An entry needs no training, so the server always sends
 * `ingested`; the other values keep the type in line with the other sources.
 */
export type QaEntryStatus = 'pending' | 'ingesting' | 'ingested' | 'failed';

export interface QaEntry {
    id: number;
    title: string;
    questions: QaQuestion[];
    answer: string;
    status: QaEntryStatus;
    created_at: string;
    updated_at: string;
}

export async function fetchQaEntries(agentId: string): Promise<QaEntry[]> {
    const response = await get<{ data?: QaEntry[] }>(qa.index.url(agentId));

    return response.data || [];
}

export function fetchQaEntry(agentId: string, id: number): Promise<QaEntry> {
    return get<QaEntry>(qa.show.url({ agent: agentId, qa: id }));
}

export async function deleteQaEntry(agentId: string, id: number): Promise<void> {
    await del(qa.destroy.url({ agent: agentId, qa: id }));
}
