/**
 * Topics API: the topic trend charts, the emergent topic suggestions, and the
 * topics attached to an agent (list, attach, create, rename, remove). Used by
 * the Topics analytics page and the Manage Topics page.
 */
import topics from '@/routes/agents/topics';
import { del, get, patch, post } from './http';

/** Topic counts per day and in total (Domain\Conversation\Topic\Data\TopicsResourceData). */
export type TopicTrends = Domain.Conversation.Topic.Data.TopicsResourceData;

/** A topic row as the topics endpoints return it (Domain\Conversation\Topic\Data\TopicRowData). */
export interface Topic {
    id: number;
    /** Null for the built-in system topics. */
    workspace_id: number | null;
    topic: string;
    description: string;
    created_at: string | null;
    updated_at: string | null;
}

/** A conversation where a suggested topic came up (Domain\Analytics\Data\Topics\SuggestedTopicExampleData). */
export interface TopicSuggestionExample {
    conversation_id: string;
    title: string | null;
}

/** An emergent topic the analysis keeps suggesting (Domain\Analytics\Data\Topics\SuggestedTopicData). */
export interface TopicSuggestion {
    label: string;
    count: number;
    examples: TopicSuggestionExample[];
}

/** The name and description of a topic to create or rename. */
export interface TopicInput {
    topic: string;
    description: string;
}

/** Confirmation message of a topic or suggestion change. */
export interface TopicMessage {
    message: string;
}

/** Confirmation of a topic change, with the stored topic. */
export interface TopicMutation extends TopicMessage {
    topic: Topic;
}

/** Topic counts per day and in total, for the topic charts. */
export function fetchTopicTrends(agentId: string): Promise<TopicTrends> {
    return get<TopicTrends>(topics.data.url(agentId));
}

/** The emergent topic suggestions for the agent, most frequent first. */
export async function fetchTopicSuggestions(agentId: string): Promise<TopicSuggestion[]> {
    const response = await get<{ suggestions?: TopicSuggestion[] }>(topics.suggestions.url(agentId));

    return response.suggestions ?? [];
}

/** Turns a suggestion into a real topic of the agent. */
export function promoteTopicSuggestion(agentId: string, label: string): Promise<TopicMutation> {
    return post<TopicMutation>(topics.suggestions.promote.url(agentId), { label });
}

/** Dismisses a suggestion so it is not shown again. */
export function dismissTopicSuggestion(agentId: string, label: string): Promise<TopicMessage> {
    return del<TopicMessage>(topics.suggestions.dismiss.url(agentId), { data: { label } });
}

/** The topics attached to the agent, sorted by name. */
export function fetchAgentTopics(agentId: string): Promise<Topic[]> {
    return get<Topic[]>(topics.index.url(agentId));
}

/** Every topic the agent may use (system topics and the workspace's own), sorted by name. */
export function fetchAvailableTopics(agentId: string): Promise<Topic[]> {
    return get<Topic[]>(topics.index.url(agentId), { params: { all: true } });
}

/** Attaches an existing topic to the agent. */
export function attachTopic(agentId: string, topicId: number): Promise<TopicMutation> {
    return post<TopicMutation>(topics.store.url(agentId), { topic_id: topicId });
}

/** Creates a new topic and attaches it to the agent. */
export function createTopic(agentId: string, input: TopicInput): Promise<TopicMutation> {
    return post<TopicMutation>(topics.store.url(agentId), input);
}

/** Renames one of the agent's topics. */
export function updateTopic(agentId: string, topicId: number, input: TopicInput): Promise<TopicMutation> {
    return patch<TopicMutation>(topics.update.url({ agent: agentId, topic: topicId }), input);
}

/** Removes a topic from the agent. */
export function detachTopic(agentId: string, topicId: number): Promise<TopicMessage> {
    return del<TopicMessage>(topics.destroy.url({ agent: agentId, topic: topicId }));
}
