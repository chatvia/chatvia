/**
 * A generated image, audio file or video as the server reports it
 * (Domain\Conversation\Data\Media\GeneratedMediaData): on history load inside
 * the `image` / `audio` / `video` artifact, on the `GeneratedMediaUpdated` broadcast,
 * and from the conversation's media endpoint. `url` is set once it is made.
 */
export interface GeneratedMedia {
    id: string;
    kind: GeneratedMediaKind;
    status: 'pending' | 'ready' | 'failed';
    url: string | null;
    mime_type: string | null;
    failure: 'refused' | 'failed' | 'timed_out' | null;
}

export type GeneratedMediaKind = 'image' | 'audio' | 'video';

/** The `image` / `audio` / `video` artifact payload: the file's state plus what was asked for. */
export interface GeneratedMediaArtifactData extends Partial<GeneratedMedia> {
    id: string;
    prompt?: string;
}

export const GENERATED_MEDIA_KINDS: readonly string[] = ['image', 'audio', 'video'];
