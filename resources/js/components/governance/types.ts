/**
 * Shapes of the tool-call audit log (AgentActivity::toAuditArray()), shared by
 * the per-agent Audit page, the workspace Governance page and AuditTable.
 */

/** One logged tool call. */
export interface AuditRow {
    id: number;
    created_at: string | null;
    outcome: string;
    agent_id: number | null;
    agent_name: string;
    agent_external_id: string | null;
    user_id: number | null;
    user_name: string | null;
    avatar: string | null;
    trace_id: string | null;
    tool_name: string | null;
    tool_kind: string | null;
    connector_slug: string | null;
    connector_name: string | null;
    is_error: boolean;
    gated: boolean;
    latency_ms: number | null;
    arguments: unknown;
    /** The arguments as text when they were cut at the size limit or were not JSON. */
    arguments_preview: string | null;
    arguments_truncated: boolean;
    result_text: string | null;
    conversation_external_id: string | null;
    /** What the visitor said when they approved out loud (a voice call's yes). */
    decision_note: string | null;
}

/**
 * Cursor paginator: the audit log is unbounded, so the backend never computes a
 * total — prev/next URLs are the whole navigation contract.
 */
export interface AuditPaginator {
    data: AuditRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}
