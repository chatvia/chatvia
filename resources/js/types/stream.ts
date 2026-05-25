/**
 * SSE envelope v1 — every event over the wire matches this shape.
 *
 * The line is always `data: <json>\n\n`. No raw `language:` / `sources:` /
 * `[DONE]` lines. Errors are first-class events, not text content.
 */

export type StreamEventType = 'start' | 'delta' | 'meta' | 'sources' | 'tool' | 'error' | 'done';

export type StreamErrorCode =
    | 'subscription_expired'
    | 'message_limit_reached'
    | 'upstream_unavailable'
    | 'rate_limited'
    | 'internal'
    | 'network'
    | 'parse';

export interface StreamStartData {
    assistant_message_id: string;
    user_message_id: string | null;
    request_id: string | null;
    language: string | null;
    replayed: boolean;
}

export interface StreamDeltaData {
    text: string;
}

export interface StreamMetaData {
    key: string;
    value: unknown;
}

export interface StreamSourcesData {
    sources: unknown[];
}

export interface StreamErrorData {
    code: StreamErrorCode;
    message: string;
    retryable: boolean;
}

export interface StreamDoneData {
    reason: 'complete' | 'aborted' | 'error' | 'partial';
    response_time_ms: number | null;
    finished_at: string | null;
}

export interface StreamToolData {
    phase: 'start' | 'delta' | 'end';
    tool_call_id: string;
    name?: string;
    args_delta?: string;
    result?: unknown;
    // Display metadata, sent on phase: 'start'. Drives the branded
    // loading indicator (icon + service name) in the chat bubble.
    kind?: 'mcp' | 'function';
    label?: string;
    icon_url?: string | null;
    brand_color?: string | null;
}

interface BaseEnvelope {
    v: 1;
    seq: number;
    id: string;
}

export type StreamEnvelope =
    | (BaseEnvelope & { type: 'start'; data: StreamStartData })
    | (BaseEnvelope & { type: 'delta'; data: StreamDeltaData })
    | (BaseEnvelope & { type: 'meta'; data: StreamMetaData })
    | (BaseEnvelope & { type: 'sources'; data: StreamSourcesData })
    | (BaseEnvelope & { type: 'tool'; data: StreamToolData })
    | (BaseEnvelope & { type: 'error'; data: StreamErrorData })
    | (BaseEnvelope & { type: 'done'; data: StreamDoneData });

export type StreamState = 'idle' | 'connecting' | 'streaming' | 'error' | 'done';

export interface StreamError {
    code: StreamErrorCode;
    message: string;
    retryable: boolean;
}

export interface StreamCallbacks {
    onStart?: (data: StreamStartData, id: string) => void;
    onDelta?: (text: string, id: string) => void;
    onMeta?: (key: string, value: unknown, id: string) => void;
    onSources?: (sources: unknown[], id: string) => void;
    onTool?: (data: StreamToolData, id: string) => void;
    onError?: (error: StreamError, id: string | null) => void;
    onDone?: (data: StreamDoneData, id: string) => void;
}
