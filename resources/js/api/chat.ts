/**
 * Hosted chat API: the conversation, message, attachment and chatter-account
 * endpoints behind resources/js/pages/chat (the dashboard playground and the
 * hosted chat). The two streaming endpoints are read over SSE by
 * `useChatStream`, so only their URLs are built here.
 */
import chat from '@/routes/chat';
import conversations from '@/routes/conversations';
import type { ChatAttachment, ChatConversationSummary, ChatterAgent, ConversationStarter, Message, MessageSource } from '@/types/chat';
import { del, get, patch, post } from './http';

/** Whether the visitor can still send messages this month (workspace and chatter limits). */
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
    return chat.stream.url(conversationId);
}

export function toolApprovalResolveUrl(approvalId: string): string {
    return chat.tools.resolve.url(approvalId);
}

export function fetchConversations(): Promise<ChatConversationSummary[]> {
    return get<ChatConversationSummary[]>(conversations.index.url());
}

/** Server search across conversation titles and message content. */
export function searchConversations(query: string): Promise<ChatConversationSummary[]> {
    return get<ChatConversationSummary[]>(conversations.search.url(), { params: { q: query } });
}

export function createConversation(message: string, attachments: ChatAttachment[]): Promise<{ id: string }> {
    return post<{ id: string }>(conversations.create.url(), { message, attachments });
}

export function renameConversation(conversationId: string | number, title: string): Promise<void> {
    return patch(conversations.update.url(conversationId), { title });
}

export function deleteConversation(conversationId: string | number): Promise<void> {
    return del(conversations.delete.url(conversationId));
}

export function deleteAllConversations(): Promise<void> {
    return del(conversations.delete.all.url());
}

export function fetchConversationMessages(conversationId: string): Promise<Message[]> {
    return get<Message[]>(conversations.messages.url(conversationId));
}

/** Knowledge sources per assistant message, keyed by the assistant message UUID. */
export function fetchConversationSources(conversationId: string): Promise<Record<string, MessageSource[]>> {
    return get<Record<string, MessageSource[]>>(chat.sources.url(conversationId));
}

/** Names the conversation from its first message. Idempotent once a title exists. */
export function generateConversationTitle(conversationId: string): Promise<{ title: string | null }> {
    return post<{ title: string | null }>(conversations.title.url(conversationId));
}

export function fetchSendCapacity(): Promise<ChatUsage> {
    return get<ChatUsage>(chat.usage.url());
}

export function fetchStarters(): Promise<ConversationStarter[]> {
    return get<ConversationStarter[]>(chat.starters.url());
}

/** Deletes a user/assistant pair so the caller can stream a new reply into the same slot. */
export function regenerateMessage(pair: MessagePairRef): Promise<RegeneratedTurn> {
    return post<RegeneratedTurn>(chat.messages.regenerate.url(), {
        conversation_id: pair.conversationId,
        user_message_id: pair.userMessageId,
        assistant_message_id: pair.assistantMessageId,
    });
}

export function sendMessageFeedback(conversationId: string, messageId: string | number, feedback: MessageFeedbackValue): Promise<void> {
    return patch(chat.messages.feedback.url(), {
        conversation_id: conversationId,
        message_id: messageId,
        feedback,
    });
}

/** Deletes a message and its paired reply (or prompt). */
export function deleteMessage(conversationId: string, messageId: string | number): Promise<void> {
    return del(chat.messages.delete.url(), {
        data: { conversation_id: conversationId, message_id: messageId },
    });
}

/** Removes a file that is already attached to a sent message. */
export function deleteConversationAttachment(conversationId: string, attachmentId: string): Promise<void> {
    return del(chat.conversations.attachments.delete.url({ conversationId, attachment: attachmentId }));
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
    return post<InlineFormSubmitResult>(chat.forms.submit.url(formInstanceId), {
        fields,
        assistant_message_id: assistantMessageId,
    });
}

/** Speech-to-text for the composer's voice input. Resolves to '' when nothing was heard. */
export async function transcribeAudio(audio: Blob, filename: string): Promise<string> {
    const form = new FormData();
    form.append('audio', audio, filename);

    const response = await post<{ text?: string }>(chat.transcribe.url(), form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return response.text || '';
}

export function fetchChatterAgents(): Promise<ChatterAgent[]> {
    return get<ChatterAgent[]>(chat.agents.index.url());
}
