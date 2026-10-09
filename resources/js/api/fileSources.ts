/**
 * File knowledge sources of an agent (documents, images, audio, video): the
 * list, uploading, starting training for uploaded files, the AI summary of a
 * trained file, and unlinking or deleting files.
 */
import { isAxiosError } from 'axios';
import { del, get, post } from './http';

export type SourceFile = Domain.Agent.Data.Resources.Sources.SourceFileDataResource;

/** The AI analysis of a trained file. Every field is optional: older files may lack some. */
export interface FileSummary {
    key_topics?: string[];
    writing_style?: string;
    target_audience?: string;
    complexity_level?: string;
    formatting_elements?: string[];
    contains_sensitive_info?: boolean;
    estimated_reading_time_minutes?: number;
}

/** The URL files are uploaded to (one file per request, field `file`). */
export function sourceFileUploadUrl(agentId: string): string {
    return route('agents.sources.files.upload', { agent: agentId });
}

/** The agent's files, optionally only one kind (`pdf`, `audio`, …). */
export function listSourceFiles(agentId: string, fileType?: string): Promise<SourceFile[]> {
    const params: Record<string, string> = {};
    if (fileType) {
        params.file_type = fileType;
    }

    return get<SourceFile[]>(route('agents.sources.files.data', { agent: agentId }), { params });
}

export function uploadSourceFile(agentId: string, file: File): Promise<void> {
    const body = new FormData();
    body.append('file', file);

    return post(sourceFileUploadUrl(agentId), body, { headers: { 'Content-Type': 'multipart/form-data' } });
}

/** Starts training every uploaded file of the agent. */
export function trainUploadedFiles(agentId: string): Promise<void> {
    return post(route('agents.sources.files.process', { agent: agentId }));
}

/** Deletes every uploaded file that was not trained yet. */
export function deleteUploadedFiles(agentId: string): Promise<void> {
    return del(route('agents.sources.uploaded-files', { agent: agentId }));
}

/** Deletes the file everywhere, for every agent that uses it. */
export function deleteSourceFile(agentId: string, fileId: number): Promise<void> {
    return del(route('agents.sources.files.delete', { agent: agentId, file: fileId }));
}

/** Removes a shared file from this agent only. */
export function unlinkSourceFile(agentId: string, fileId: number): Promise<void> {
    return post(route('agents.sources.files.unlink', { agent: agentId, file: fileId }), { file_id: fileId });
}

/** The file's summary, or null when it has none (404). Other failures throw. */
export async function fetchFileSummary(agentId: string, fileId: number): Promise<FileSummary | null> {
    try {
        return await get<FileSummary>(route('agents.agent.files.summary', { agent: agentId, file: fileId }));
    } catch (error) {
        if (isAxiosError(error) && error.response?.status === 404) {
            return null;
        }
        throw error;
    }
}
