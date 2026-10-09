/**
 * The agent's YouTube sources: list the videos, delete one, or train one
 * again. Adding a video is an Inertia form visit on the page.
 */
import { del, get, post } from './http';

/** A video's training status (the server's SourceStatus values). */
export type YoutubeVideoStatus = 'pending' | 'ingesting' | 'ingested' | 'failed';

export interface YoutubeVideo {
    id: number;
    url: string;
    title: string | null;
    status: YoutubeVideoStatus;
    /** Why training failed, when it did. */
    failure_reason: string | null;
    created_at: string;
}

export function fetchYoutubeVideos(agentId: string): Promise<YoutubeVideo[]> {
    return get<YoutubeVideo[]>(route('agents.sources.youtube.index', { agent: agentId }));
}

export async function deleteYoutubeVideo(agentId: string, videoId: number): Promise<void> {
    await del(route('agents.sources.youtube.destroy', { agent: agentId, youtube: videoId }));
}

export async function retrainYoutubeVideo(agentId: string, videoId: number): Promise<void> {
    await post(route('agents.sources.youtube.retrain', { agent: agentId, youtube: videoId }));
}
