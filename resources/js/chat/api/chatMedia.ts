/**
 * Hosted chat API for the images, audio and videos an agent makes: how a
 * conversation's files stand, asked for while one is still being made.
 */
import conversations from '@/routes/conversations';
import { get } from '@/shared/api/http';
import type { GeneratedMedia } from '@/shared/types/generatedMedia';

export function fetchGeneratedMedia(conversationId: string): Promise<GeneratedMedia[]> {
    return get<GeneratedMedia[]>(conversations.media.url({ conversationId }));
}
