/**
 * SSE envelope v1 — every event over the wire matches this shape.
 *
 * The line is always `data: <json>\n\n`. No raw `language:` / `sources:` /
 * `[DONE]` lines. Errors are first-class events, not text content.
 */

export type StreamEventType = 'start' | 'delta' | 'meta' | 'sources' | 'tool' | 'replace' | 'error' | 'done';

export type StreamErrorCode =
    'message_limit_reached' | 'chatter_message_limit_reached' | 'upstream_unavailable' | 'rate_limited' | 'internal' | 'network' | 'parse';

export interface StreamStartData {
    assistant_message_id: string;
    user_message_id: string | null;
    /**
     * The persisted user message, PII-masked by the input guardrail. Present on
     * live turns so the client can swap the raw text it rendered optimistically.
     * Absent on replay (the stored message is already masked).
     */
    user_message?: string | null;
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

/**
 * Structured side-channel payload attached to a tool result. The LLM does
 * NOT see these — they're picked up by the chat renderer to mount inline
 * widgets (charts, etc.). Each artifact is self-describing via `kind`;
 * renderers switch on it.
 */
export interface ToolArtifact {
    /** Discriminator. `echarts_option` mounts <EChartBlock>, `form` mounts <InlineFormBlock>. */
    kind: 'echarts_option' | 'form' | string;
    /** Free-form payload — interpretation is up to the kind-specific renderer. */
    data: unknown;
}

/**
 * `form` artifact payload (RenderFormTool). The schema is FormKit-ready,
 * built server-side from the form definition's normalized fields.
 */
export interface FormArtifactData {
    form_instance_id: string;
    definition_slug: string;
    form_type: string;
    title?: string;
    subtitle?: string;
    schema: Record<string, unknown>[];
    submit_label?: string;
    success_message?: string;
    status?: 'pending' | 'submitted' | 'expired';
    expires_at?: string | null;
}

export interface StreamToolData {
    phase: 'start' | 'delta' | 'end' | 'awaiting_approval';
    tool_call_id: string;
    name?: string;
    args_delta?: string;
    // The tool's argument JSON (sent on 'start' and 'awaiting_approval') —
    // shown in the approval card so the user can judge what will run.
    args?: string;
    result?: unknown;
    // Display metadata, sent on phase: 'start' / 'awaiting_approval'. Drives
    // the branded loading indicator (icon + service name) in the chat bubble.
    kind?: 'mcp' | 'function';
    label?: string;
    // Transient per-connector activity phrase ("Preparing your form…"), shown
    // verbatim while the tool runs. Absent for tools without a configured phrase
    // (the UI falls back to the label-based "Calling {label}…").
    activity_label?: string | null;
    // First-party builtin capability (forms, charts, leads). Internal plumbing —
    // the client suppresses the activity indicator entirely for these (artifacts
    // on the 'end' phase still render). Set on phase: 'start'.
    internal?: boolean;
    icon_url?: string | null;
    brand_color?: string | null;
    // Sent on phase: 'awaiting_approval' — the checkpoint the client posts its
    // decision against, plus a human-readable reason for the prompt.
    approval_id?: string;
    reason?: string | null;
    // Sent on phase: 'end' when the tool returned structured side-channel
    // payloads (e.g. an ECharts option). Absent for plain text tools.
    artifacts?: ToolArtifact[];
}

/**
 * A tool call paused awaiting the user's approval. Built by Chat.vue from a
 * `tool` envelope with phase `awaiting_approval`; rendered as a ToolApprovalCard.
 */
export interface ToolApprovalCall {
    tool_call_id: string;
    approval_id: string;
    name: string;
    label: string;
    kind: 'mcp' | 'function';
    icon_url: string | null;
    brand_color: string | null;
    /** The tool's argument JSON, shown (parsed) so the user can judge what runs. */
    args: string;
    reason: string | null;
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
    | (BaseEnvelope & { type: 'replace'; data: StreamReplaceData })
    | (BaseEnvelope & { type: 'error'; data: StreamErrorData })
    | (BaseEnvelope & { type: 'done'; data: StreamDoneData });

/** An output guardrail corrected the answer: replace the rendered message with `text`. */
export interface StreamReplaceData {
    text: string;
}

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
    onReplace?: (text: string, id: string) => void;
    onError?: (error: StreamError, id: string | null) => void;
    onDone?: (data: StreamDoneData, id: string) => void;
}
