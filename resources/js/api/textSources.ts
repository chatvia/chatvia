/**
 * The agent's Text sources: list the texts the agent uses, read one text in
 * full (to edit it), unlink a shared text from the agent, or delete it for
 * every agent. Creating and updating a text are Inertia form visits on the page.
 */
import { del, get, post } from './http';

/** A text's training status (the server's SourceStatus values a text can have). */
export type TextSourceStatus = 'pending' | 'ingesting' | 'ingested' | 'failed';

/** One row of the agent's text list. */
export interface TextSource {
    id: number;
    excerpt: string;
    status: TextSourceStatus;
    created_at: string;
    updated_at: string;
    word_count: number;
    /** Training progress, 0-100. */
    progress: number;
    /** How many agents use this text; above one it is shared. */
    agents_count: number;
}

/** A text with its full content, as the edit form needs it. */
export interface TextSourceDetail {
    id: number;
    excerpt: string;
    content: string;
}

export function fetchTextSources(agentId: string): Promise<TextSource[]> {
    return get<TextSource[]>(route('agents.sources.text.index', { agent: agentId }));
}

export function fetchTextSource(agentId: string, id: number): Promise<TextSourceDetail> {
    return get<TextSourceDetail>(route('agents.sources.text.show', { agent: agentId, id }));
}

/** Removes the text from this agent only; other agents keep it. */
export async function unlinkTextSource(agentId: string, id: number): Promise<void> {
    await post(route('agents.sources.text.unlink', { agent: agentId, id }));
}

/** Deletes the text for every agent that uses it. */
export async function deleteTextSource(agentId: string, id: number): Promise<void> {
    await del(route('agents.sources.text.destroy', { agent: agentId, id }));
}
