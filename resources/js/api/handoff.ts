/**
 * Live-chat handoff API: an operator takes a widget conversation over from the
 * AI, replies as a human, signals typing and hands the conversation back.
 * Real-time updates arrive over the conversation's presence channel (see
 * `useHandoff`); these calls only change state on the server.
 */
import { post } from './http';

export type HandoffTakeoverResult = App.Http.Resources.Handoff.HandoffTakeoverResultData & {
    admin_id?: number | null;
    admin_name?: string | null;
};
export type HandoffReturnResult = App.Http.Resources.Handoff.HandoffReturnResultData;
export type HandoffMessageResult = App.Http.Resources.Handoff.HandoffMessageResultData;

interface HandoffTarget {
    agentId: string;
    conversationId: string;
}

function params(target: HandoffTarget): { agent: string; conversation: string } {
    return { agent: target.agentId, conversation: target.conversationId };
}

export function takeOverConversation(target: HandoffTarget): Promise<HandoffTakeoverResult> {
    return post<HandoffTakeoverResult>(route('agents.handoff.takeover', params(target)));
}

export function returnConversationToAi(target: HandoffTarget): Promise<HandoffReturnResult> {
    return post<HandoffReturnResult>(route('agents.handoff.return', params(target)));
}

export function sendHandoffMessage(target: HandoffTarget, content: string): Promise<HandoffMessageResult> {
    return post<HandoffMessageResult>(route('agents.handoff.message', params(target)), { content });
}

export function sendHandoffTyping(target: HandoffTarget, isTyping: boolean): Promise<void> {
    return post(route('agents.handoff.typing', params(target)), { is_typing: isTyping });
}

export function markHandoffMessagesRead(target: HandoffTarget): Promise<void> {
    return post(route('agents.handoff.mark-read', params(target)));
}
