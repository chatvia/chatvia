/**
 * Client-side shapes for the hosted chat surface (resources/js/pages/chat) and
 * its components. The server builds these as plain arrays (ChatMemory,
 * MessageAttachmentHydrator), so they are declared here rather than generated.
 */
import type { StreamErrorCode, ToolArtifact } from './stream';

/** A file the chatter attached to a message (Domain\Conversation\Data\Attachments\AttachmentData). */
export interface ChatAttachment {
    id: string;
    url: string;
    filename: string;
    mime_type: string;
    size_bytes: number;
    pages?: number | null;
}

/**
 * One row of the hosted chat transcript. Optimistic stubs use `id: 0` until the
 * stream's `start` event hands back the real UUID; `role: 'error'` rows are
 * inline error bubbles that replace a reply which never started.
 */
export interface Message {
    id?: string | number | null;
    content: string;
    role: 'user' | 'assistant' | 'error';
    model?: string;
    language?: string | null;
    created_at?: string;
    updated_at?: string;
    feedback?: 'positive' | 'negative' | null;
    /** Attachments merged onto the message by the server, or kept from the composer for fresh turns. */
    attachments?: ChatAttachment[];
    /**
     * Free-form JSON persisted on the message (`chat_messages.metadata`). It
     * carries chart `artifacts` (so charts re-render on reload) and a copy of
     * the attachments.
     */
    metadata?: {
        artifacts?: ToolArtifact[];
        attachments?: ChatAttachment[];
    } | null;
    errorCode?: StreamErrorCode;
    errorRetryable?: boolean;
}

/** A knowledge-base chunk that grounded an assistant reply (shown in SourcesDialog). */
export interface MessageSource {
    content: string;
    metadata?: {
        title?: string;
        file_name?: string;
        page?: number;
        chunk_index?: number;
        [key: string]: unknown;
    };
}

/** A tool call that is running right now; drives the "Calling …" indicator. */
export interface ActiveToolCall {
    tool_call_id: string;
    name: string;
    kind: 'mcp' | 'function';
    label: string;
    activity_label: string | null;
    icon_url: string | null;
    brand_color: string | null;
    started_at: number;
}

/** A conversation row in the hosted chat sidebar and search palette. */
export interface ChatConversationSummary {
    id: string | number;
    title: string;
    /** Unix seconds; used by the search dialog's date buckets. */
    timestamp?: number;
}

/** An agent the signed-in chatter may switch to. */
export interface ChatterAgent {
    id: number;
    external_id: string;
    name: string;
    sub_domain: string;
}

/** A conversation starter as stored on the agent (resolved per locale on the client). */
export interface ConversationStarter {
    text: string;
    icon?: string | null;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

/** The colour theme the hosted chat page follows when the theme switcher is hidden. */
export type ChatDefaultTheme = 'auto' | 'light' | 'dark';

/**
 * The agent's appearance settings shared with the hosted chat pages. Only the
 * fields the pages read are typed; the rest pass through to ChatLayout.
 */
export interface ChatAppearance {
    color_theme?: string;
    color_shade?: number;
    welcome_message?: string | null;
    chat_window_title?: string | null;
    avatar?: string | null;
    logo?: string | null;
    composer_placeholder?: string | null;
    allow_file_uploads?: boolean;
    allow_voice_input?: boolean;
    [key: string]: unknown;
}
