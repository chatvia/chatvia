/**
 * File knowledge sources of an agent (documents, images, audio, video): the
 * list, uploading, starting training for uploaded files, the AI summary of a
 * trained file, and unlinking or deleting files.
 */
import agentFiles from '@/routes/agents/agent/files';
import { uploadedFiles } from '@/routes/agents/sources';
import files from '@/routes/agents/sources/files';
import { del, get, post } from '@/shared/api/http';
import type { LengthAwarePage } from '@/shared/api/pagination';
import { isAxiosError } from 'axios';

export type SourceFile = Domain.Agent.Data.Resources.Sources.SourceFileResourceData;

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
    return files.upload.url(agentId);
}

/** Which files a list holds: those waiting to be trained, or the others (training, trained or failed). */
export type SourceFileStage = 'uploaded' | 'trained';

/** One page (50 files, newest first) of the agent's files at one stage, optionally only one kind (`pdf`, `audio`, …). */
export function listSourceFiles(agentId: string, stage: SourceFileStage, page: number, fileType?: string): Promise<LengthAwarePage<SourceFile>> {
    const params: Record<string, string | number> = { stage, page };
    if (fileType) {
        params.file_type = fileType;
    }

    return get<LengthAwarePage<SourceFile>>(files.data.url(agentId), { params });
}

export function uploadSourceFile(agentId: string, file: File): Promise<void> {
    const body = new FormData();
    body.append('file', file);

    return post(sourceFileUploadUrl(agentId), body, { headers: { 'Content-Type': 'multipart/form-data' } });
}

/** Puts a new version in place of a trained file; the server trains it again. */
export function replaceSourceFile(agentId: string, fileId: number, file: File): Promise<void> {
    const body = new FormData();
    body.append('file', file);

    return post(files.replace.url({ agent: agentId, file: fileId }), body, { headers: { 'Content-Type': 'multipart/form-data' } });
}

/** Starts training every uploaded file of the agent. */
export function trainUploadedFiles(agentId: string): Promise<void> {
    return post(files.process.url(agentId));
}

/** Deletes every uploaded file that was not trained yet. */
export function deleteUploadedFiles(agentId: string): Promise<void> {
    return del(uploadedFiles.url(agentId));
}

/** Deletes the file everywhere, for every agent that uses it. */
export function deleteSourceFile(agentId: string, fileId: number): Promise<void> {
    return del(files.delete.url({ agent: agentId, file: fileId }));
}

/** Removes a shared file from this agent only. */
export function unlinkSourceFile(agentId: string, fileId: number): Promise<void> {
    return post(files.unlink.url({ agent: agentId, file: fileId }), { file_id: fileId });
}

/** The file's summary, or null when it has none (404). Other failures throw. */
export async function fetchFileSummary(agentId: string, fileId: number): Promise<FileSummary | null> {
    try {
        return await get<FileSummary>(agentFiles.summary.url({ agent: agentId, file: fileId }));
    } catch (error) {
        if (isAxiosError(error) && error.response?.status === 404) {
            return null;
        }
        throw error;
    }
}
