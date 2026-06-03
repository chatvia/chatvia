export interface Message {
    id?: string | number;
    content: string;
    role: 'user' | 'assistant' | 'error';
    model?: string;
    created_at?: string;
    updated_at?: string;
    feedback?: 'positive' | 'negative' | null;
    sources?: any[];
    /**
     * Free-form JSON metadata persisted on the message. Today it carries
     * `artifacts` (chart specs) so the assistant bubble can re-render
     * charts on page reload. The backend stores this in
     * `chat_messages.metadata`.
     */
    metadata?: {
        artifacts?: import('./stream').ToolArtifact[];
        attachments?: Array<{
            id: string;
            url: string;
            filename: string;
            mime_type: string;
            size_bytes: number;
        }>;
    } | null;
    errorCode?: import('./stream').StreamErrorCode;
    errorRetryable?: boolean;
}

export type AIModel = Domain.Llm.Data.LlmModelData;

export interface Conversation {
    id: string;
    title: string;
    created_at: string;
    updated_at: string;
    messages?: Message[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}
