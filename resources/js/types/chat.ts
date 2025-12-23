export interface Message {
    id?: string | number;
    content: string;
    role: 'user' | 'assistant';
    model?: string;
    created_at?: string;
    updated_at?: string;
    feedback?: boolean;
    sources?: any[];
}

export type AIModel = App.Data.Chat.LlmModelData;

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
