/**
 * The workspace's shared source pool, as seen from one agent: trained files,
 * texts and websites the agent does not use yet, and the calls that attach one
 * of them to the agent. Used by the "attach existing source" dialogs.
 */
import { get, post } from './http';

/** Another agent that already uses a source (shown as "used in N agents"). */
export interface SourceAgentRef {
    id: number;
    name: string;
}

export interface AttachableFile {
    id: number;
    filename: string;
    filetype: string;
    length: string;
    agents?: SourceAgentRef[];
}

export interface AttachableText {
    id: number;
    excerpt: string;
    word_count: number;
    agents?: SourceAgentRef[];
}

export interface AttachableWebsite {
    id: number;
    url: string;
    urls_count: number;
    created_at: string;
    agents?: SourceAgentRef[];
}

/** Trained files of the given type (pdf, word, audio, …) the agent does not use yet. */
export function fetchAttachableFiles(agentId: string, fileType?: string): Promise<AttachableFile[]> {
    const params: Record<string, string> = {};
    if (fileType) {
        params.type = fileType;
    }

    return get<AttachableFile[]>(route('agents.sources.list', { agent: agentId }), { params });
}

export function attachFile(agentId: string, fileId: number): Promise<void> {
    return post(route('agents.sources.files.link', { agent: agentId, fileId }), { file_id: fileId });
}

/** Trained texts the agent does not use yet. */
export function fetchAttachableTexts(agentId: string): Promise<AttachableText[]> {
    return get<AttachableText[]>(route('agents.sources.texts.list', { agent: agentId }));
}

export function attachText(agentId: string, textId: number): Promise<void> {
    return post(route('agents.sources.texts.link', { agent: agentId, textId }), { text_id: textId });
}

/** Trained websites the agent does not use yet. */
export function fetchAttachableWebsites(agentId: string | number): Promise<AttachableWebsite[]> {
    return get<AttachableWebsite[]>(route('agents.sources.trained-websites.index', { agent: agentId }));
}

export function attachWebsite(agentId: string | number, websiteId: number): Promise<void> {
    return post(route('agents.sources.trained-websites.link', { agent: agentId, websiteId }), { website_id: websiteId });
}
