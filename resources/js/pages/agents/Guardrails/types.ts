/**
 * Shapes for the agent Guardrails page: the saved settings, the PII filter
 * catalog, one row of the trigger log (GuardrailEvent::toLogArray()) and the
 * log's filters.
 */

export type GuardMode = 'block' | 'monitor';
export type GuardSensitivity = 'low' | 'medium' | 'high';
export type PiiAction = 'mask' | 'block';
export type TopicAction = 'refuse' | 'reply' | 'handoff';

/** A topic the agent must not discuss, and what the visitor gets instead. */
export interface BlockedTopic {
    name: string;
    description: string | null;
    examples: string[];
    action: TopicAction;
    reply: string | null;
}

/** An owner-defined kind of personal data, found by a regular expression. */
export interface CustomPattern {
    name: string;
    pattern: string;
    action: PiiAction;
}

export interface GuardSettings {
    enabled: boolean;
    filters: string[];
    /** Filter key → action; a missing key masks. */
    pii_actions: Record<string, PiiAction>;
    custom_patterns: CustomPattern[];
    /** Values the PII filters never mask: emails, `@domain`s, numbers. */
    allowlist: string[];
    injection: boolean;
    injection_mode: GuardMode;
    injection_sensitivity: GuardSensitivity;
    toxicity: boolean;
    toxicity_mode: GuardMode;
    toxicity_sensitivity: GuardSensitivity;
    blocked_topics: BlockedTopic[];
    allowed_scope: string;
    topics_mode: GuardMode;
    refusal_message: string;
    check_before_showing: boolean;
    alert_owner: boolean;
}

/** The trigger log's filters; null shows every value. */
export interface GuardrailLogFilters {
    guard_type: string | null;
    outcome: string | null;
}

/** One PII filter the agent can switch on, e.g. `{ key: 'email', label: 'Email addresses' }`. */
export interface PiiFilterOption {
    key: string;
    label: string;
}

/** One logged guardrail trigger. */
export interface GuardrailTrigger {
    id: number;
    external_id: string;
    created_at: string | null;
    agent_id: number | null;
    agent_name: string | null;
    agent_external_id: string | null;
    user_id: number | null;
    user_name: string | null;
    avatar: string | null;
    stage: string;
    guard_type: string;
    guard_label: string;
    outcome: string;
    severity: string | null;
    detail: unknown;
    snippet: string | null;
    trace_id: string | null;
    conversation_external_id: string | null;
}

/** Laravel length-aware paginator of trigger rows. */
export interface GuardrailTriggerPage {
    data: GuardrailTrigger[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
