/**
 * Hosted chat API for a turn still being answered: a chat reloaded while its
 * reply streamed asks whether that reply is still coming.
 */
import conversations from '@/routes/conversations';
import { get } from './http';

export async function isReplyRunning(conversationId: string): Promise<boolean> {
    const status = await get<{ running: boolean }>(conversations.replyStatus.url({ conversationId }));

    return status.running;
}
