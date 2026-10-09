/**
 * The agent's YouTube sources: list the videos, add one by its link, delete
 * one, or train one again.
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

/** Adds a video by its link. A refused link rejects with a 422 whose `url` field error says why. */
export async function addYoutubeVideo(agentId: string, url: string): Promise<void> {
    await post(route('agents.sources.youtube.store', { agent: agentId }), { url });
}

export async function deleteYoutubeVideo(agentId: string, videoId: number): Promise<void> {
    await del(route('agents.sources.youtube.destroy', { agent: agentId, youtube: videoId }));
}

export async function retrainYoutubeVideo(agentId: string, videoId: number): Promise<void> {
    await post(route('agents.sources.youtube.retrain', { agent: agentId, youtube: videoId }));
}
