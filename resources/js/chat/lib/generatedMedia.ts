/**
 * Reading a reply's generated images, audio and videos out of its artifacts.
 * While one is being made, the reply's text is the model's "on its way"
 * sentence (the media tools ask for just that); once none is left, the
 * hosted chat shows a done line in its place, from {@see settledMedia}.
 */
import { GENERATED_MEDIA_KINDS, type GeneratedMediaArtifactData, type GeneratedMediaKind } from '@/shared/types/generatedMedia';
import type { ToolArtifact } from '@/shared/types/stream';

/** What a reply's files came to once every one is made or failed. */
export interface SettledMedia {
    /** How many were made. */
    ready: number;
    /** Their kind when all made ones share it, else null. */
    kind: GeneratedMediaKind | null;
}

export function isMediaArtifact(artifact: ToolArtifact): artifact is ToolArtifact & { data: GeneratedMediaArtifactData } {
    return GENERATED_MEDIA_KINDS.includes(artifact.kind) && typeof (artifact.data as GeneratedMediaArtifactData | null)?.id === 'string';
}

/** Null while the reply has no generated file or one is still being made. */
export function settledMedia(artifacts: ToolArtifact[]): SettledMedia | null {
    const media = artifacts.filter(isMediaArtifact);
    if (media.length === 0 || media.some((artifact) => (artifact.data.status ?? 'pending') === 'pending')) return null;

    const ready = media.filter((artifact) => artifact.data.status === 'ready');
    const kinds = new Set(ready.map((artifact) => artifact.kind as GeneratedMediaKind));

    return { ready: ready.length, kind: kinds.size === 1 ? [...kinds][0] : null };
}
