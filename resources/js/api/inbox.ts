/**
 * Inbox API: everyone chatting with the workspace's agents right now, and
 * how many of them wait for a human. Joining and replying go through the
 * per-agent handoff API (`@/api/handoff`), keyed by each row's agent.
 */
import { conversations, waitingCount } from '@/routes/inbox';
import { get } from './http';

export type InboxConversation = App.Http.Resources.Inbox.InboxConversationData;
export type InboxConversationsPage = App.Http.Resources.Inbox.InboxConversationsResourceData;

export function fetchInboxConversations(): Promise<InboxConversationsPage> {
    return get<InboxConversationsPage>(conversations.url());
}

export async function fetchInboxWaitingCount(): Promise<number> {
    const result = await get<App.Http.Resources.Inbox.InboxWaitingCountData>(waitingCount.url());

    return result.count;
}
