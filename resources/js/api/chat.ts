/**
 * Hosted chat API: the conversation, message, attachment and chatter-account
 * endpoints behind resources/js/pages/chat (the dashboard playground and the
 * hosted chat). The two streaming endpoints are read over SSE by
 * `useChatStream`, so only their URLs are built here.
 */
import type { ChatAttachment, ChatConversationSummary, ChatterAgent, ConversationStarter, Message, MessageSource } from '@/types/chat';
import { del, get, patch, post } from './http';

/** Whether the agent owner still has message capacity this billing period. */
export interface ChatUsage {
    can_send_messages: boolean;
}

/** The server deleted the old pair and returns what the re-streamed turn needs. */
export interface RegeneratedTurn {
    success: boolean;
    user_message_content: string;
    created_at: string;
}

export interface MessagePairRef {
    conversationId: string;
    userMessageId: string | number;
    assistantMessageId: string | number;
}

/** Response of an inline form submit (Domain\Form\Data\FormSubmitResultData). */
export interface InlineFormSubmitResult {
    status: string;
    success_message?: string;
    submitted_values?: Record<string, unknown>;
}

export type MessageFeedbackValue = 'positive' | 'negative' | null;

export function chatStreamUrl(conversationId: string): string {
    return route('chat.stream', { conversationId });
}

export function toolApprovalResolveUrl(approvalId: string): string {
    return route('chat.tools.resolve', { approval: approvalId });
}

export function fetchConversations(): Promise<ChatConversationSummary[]> {
    return get<ChatConversationSummary[]>(route('conversations.index'));
}

/** Server search across conversation titles and message content. */
export function searchConversations(query: string): Promise<ChatConversationSummary[]> {
    return get<ChatConversationSummary[]>(route('conversations.search'), { params: { q: query } });
}

export function createConversation(message: string, attachments: ChatAttachment[]): Promise<{ id: string }> {
    return post<{ id: string }>(route('conversations.create'), { message, attachments });
}

export function renameConversation(conversationId: string | number, title: string): Promise<void> {
    return patch(route('conversations.update', { conversationId }), { title });
}

export function deleteConversation(conversationId: string | number): Promise<void> {
    return del(route('conversations.delete', { conversationId }));
}

export function deleteAllConversations(): Promise<void> {
    return del(route('conversations.delete.all'));
}

export function fetchConversationMessages(conversationId: string): Promise<Message[]> {
    return get<Message[]>(route('conversations.messages', { conversationId }));
}

/** Knowledge sources per assistant message, keyed by the assistant message UUID. */
export function fetchConversationSources(conversationId: string): Promise<Record<string, MessageSource[]>> {
    return get<Record<string, MessageSource[]>>(route('chat.sources', { conversationId }));
}

/** Names the conversation from its first message. Idempotent once a title exists. */
export function generateConversationTitle(conversationId: string): Promise<{ title: string | null }> {
    return post<{ title: string | null }>(route('conversations.title', { conversationId }));
}

export function fetchUsage(): Promise<ChatUsage> {
    return get<ChatUsage>(route('chat.usage'));
}

export function fetchStarters(): Promise<ConversationStarter[]> {
    return get<ConversationStarter[]>(route('chat.starters'));
}

/** Deletes a user/assistant pair so the caller can stream a new reply into the same slot. */
export function regenerateMessage(pair: MessagePairRef): Promise<RegeneratedTurn> {
    return post<RegeneratedTurn>(route('chat.messages.regenerate'), {
        conversation_id: pair.conversationId,
        user_message_id: pair.userMessageId,
        assistant_message_id: pair.assistantMessageId,
    });
}

export function sendMessageFeedback(conversationId: string, messageId: string | number, feedback: MessageFeedbackValue): Promise<void> {
    return patch(route('chat.messages.feedback'), {
        conversation_id: conversationId,
        message_id: messageId,
        feedback,
    });
}

/** Deletes a message and its paired reply (or prompt). */
export function deleteMessage(conversationId: string, messageId: string | number): Promise<void> {
    return del(route('chat.messages.delete'), {
        data: { conversation_id: conversationId, message_id: messageId },
    });
}

/** Removes a file that is already attached to a sent message. */
export function deleteConversationAttachment(conversationId: string, attachmentId: string): Promise<void> {
    return del(route('chat.conversations.attachments.delete', { conversationId, attachment: attachmentId }));
}

/**
 * Uploads a composer attachment. The URL and headers come from the page that
 * hosts the composer, because each surface scopes uploads differently.
 */
export async function uploadAttachment(url: string, file: File, headers: Record<string, string> = {}): Promise<ChatAttachment> {
    const form = new FormData();
    form.append('file', file);

    const response = await post<{ data: ChatAttachment }>(url, form, {
        headers: { 'Content-Type': 'multipart/form-data', ...headers },
    });

    return response.data;
}

/** Removes a composer attachment that has not been sent yet. */
export function deleteAttachment(url: string): Promise<void> {
    return del(url);
}

export function submitInlineForm(
    formInstanceId: string,
    fields: Record<string, unknown>,
    assistantMessageId?: string,
): Promise<InlineFormSubmitResult> {
    return post<InlineFormSubmitResult>(route('chat.forms.submit', { formInstance: formInstanceId }), {
        fields,
        assistant_message_id: assistantMessageId,
    });
}

/** Speech-to-text for the composer's voice input. Resolves to '' when nothing was heard. */
export async function transcribeAudio(audio: Blob, filename: string): Promise<string> {
    const form = new FormData();
    form.append('audio', audio, filename);

    const response = await post<{ text?: string }>(route('chat.transcribe'), form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return response.text || '';
}

export function fetchChatterAgents(): Promise<ChatterAgent[]> {
    return get<ChatterAgent[]>(route('chat.agents.index'));
}
