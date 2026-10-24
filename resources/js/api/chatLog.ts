/**
 * Chat log API (dashboard → agent → Chat Log): conversation list with filters,
 * a conversation's messages, the sentiment "pulse", filter options, deletion and
 * the "revise answer" Q&A shortcut. Every endpoint is scoped to an agent by its
 * external id.
 */
import { logs as chatLogPage } from '@/routes/agents';
import chatLog from '@/routes/agents/logs';
import { del, get, post } from './http';

export type ChatLogConversation = Domain.Agent.Data.Resources.ChatLog.Conversations.ChatLogConversationData;
export type ChatLogMessage = Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageData;
export type ChatLogLead = Domain.Agent.Data.Resources.ChatLog.Handoff.ChatLogLeadData;
export type ChatLogHandoffStatus = Domain.Agent.Data.Resources.ChatLog.Handoff.ChatLogHandoffStatusData;
export type ChatLogPulse = Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseResourceData;
export type ChatLogTopic = Domain.Agent.Data.Resources.ChatLog.Topics.ChatLogTopicData;
export type ChatLogCountry = Domain.Agent.Data.Resources.ChatLog.Countries.ChatLogCountryData;

/**
 * A page of results as Laravel's LengthAwarePaginator serialises it: the rows
 * plus flat paging fields. (The generated `Illuminate.LengthAwarePaginator`
 * type describes the resource-collection shape with a `meta` block instead.)
 */
export interface LaravelPage<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
}

/** Query filters for the conversation list. Only set keys are sent; no cursor means the first page. */
export interface ChatLogConversationFilters {
    cursor?: string;
    topic?: string;
    sentiment?: string;
    tone?: string;
    country?: string;
    confidence_score?: string;
    feedback?: string;
    source?: string;
    online_only?: boolean;
    start_date?: string;
    end_date?: string;
}

/** One page of the conversation list; `next_cursor` loads the next one and is null on the last page. */
export interface ChatLogConversationsPage {
    conversations: ChatLogConversation[];
    next_cursor: string | null;
    lead_conversation_ids: number[];
}

export interface ChatLogMessagesPage {
    messages: LaravelPage<ChatLogMessage>;
    conversation_title: string | null;
    lead: ChatLogLead | null;
    handoff: ChatLogHandoffStatus | null;
}

export interface ReviseAnswerPayload {
    message_id: string;
    user_message: string;
    expected_response: string;
    /** Also add an eval case judged against the corrected answer (needs the eval permission). */
    add_eval_case?: boolean;
}

export interface ReviseAnswerResult {
    qaId: number;
    /** False when not asked for, or when the question already was an eval case. */
    evalCaseCreated: boolean;
}

export function fetchChatLogConversations(agentId: string, filters: ChatLogConversationFilters): Promise<ChatLogConversationsPage> {
    return get<ChatLogConversationsPage>(chatLog.conversations.url(agentId), { params: filters });
}

export function fetchChatLogMessages(agentId: string, conversationId: string, page: number): Promise<ChatLogMessagesPage> {
    return get<ChatLogMessagesPage>(chatLog.messages.url({ agent: agentId, conversation: conversationId }), {
        params: { page },
    });
}

export function fetchConversationPulse(agentId: string, conversationId: string): Promise<ChatLogPulse> {
    return get<ChatLogPulse>(chatLog.pulse.url({ agent: agentId, conversation: conversationId }));
}

export async function fetchChatLogTopics(agentId: string): Promise<ChatLogTopic[]> {
    const response = await get<{ topics?: ChatLogTopic[] }>(chatLog.topics.url(agentId));

    return response.topics || [];
}

export async function fetchChatLogCountries(agentId: string): Promise<ChatLogCountry[]> {
    const response = await get<{ countries?: ChatLogCountry[] }>(chatLog.countries.url(agentId));

    return response.countries || [];
}

export function deleteChatLogConversation(agentId: string, conversationId: string): Promise<void> {
    return del(chatLog.deleteConversation.url({ agent: agentId, conversation: conversationId }));
}

/** Stores the operator's corrected answer as a Q&A entry, and as an eval case when asked. */
export async function reviseAnswer(agentId: string, payload: ReviseAnswerPayload): Promise<ReviseAnswerResult> {
    const response = await post<{ qa_id: number; eval_case_created?: boolean }>(chatLog.reviseAnswer.url(agentId), payload);

    return { qaId: response.qa_id, evalCaseCreated: response.eval_case_created === true };
}

/** Address of the chat log page, used to keep the browser URL in step with the selection. */
export function chatLogUrl(agentId: string, conversationId: string | null): string {
    return chatLogPage.url(agentId, {
        query: {
            conversation_id: conversationId || undefined,
        },
    });
}
