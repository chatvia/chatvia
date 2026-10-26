/**
 * Hosted chat API for a turn still being answered: a chat reloaded while its
 * reply streamed asks whether that reply is still coming, and the Stop button
 * ends a turn (it keeps running when the browser leaves).
 */
import chatConversations from '@/routes/chat/conversations';
import conversations from '@/routes/conversations';
import { get, post } from './http';

export async function isReplyRunning(conversationId: string): Promise<boolean> {
    const status = await get<{ running: boolean }>(conversations.replyStatus.url({ conversationId }));

    return status.running;
}

/** Ends the turn the chat streamed with this request id; resolves once it has ended. */
export function stopTurn(conversationId: string, requestId: string): Promise<void> {
    return post(chatConversations.stop.url({ conversationId }), { request_id: requestId });
}
