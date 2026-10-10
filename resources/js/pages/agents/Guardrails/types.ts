/**
 * Shapes for the agent Guardrails page: the saved settings, the PII filter
 * catalog and one row of the trigger log (GuardrailEvent::toLogArray()).
 */

export interface GuardSettings {
    enabled: boolean;
    filters: string[];
    injection: boolean;
    toxicity: boolean;
    blocked_topics: string[];
    allowed_scope: string;
    refusal_message: string;
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
