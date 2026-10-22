/**
 * i18n keys for the enum values the Guardrails page shows (PII filters, guard
 * types, outcomes, stages, severities, modes, sensitivities and actions). The
 * maps are static so every key stays a literal the i18n checks can see; an
 * unknown value is shown as it is.
 */

export const FILTER_LABELS: Record<string, string> = {
    email: 'agents.guardrails.filter_labels.email',
    iban: 'agents.guardrails.filter_labels.iban',
    credit_card: 'agents.guardrails.filter_labels.credit_card',
    us_ssn: 'agents.guardrails.filter_labels.us_ssn',
    german_ssn: 'agents.guardrails.filter_labels.german_ssn',
    uk_nino: 'agents.guardrails.filter_labels.uk_nino',
    danish_cpr: 'agents.guardrails.filter_labels.danish_cpr',
    ip_address: 'agents.guardrails.filter_labels.ip_address',
    phone: 'agents.guardrails.filter_labels.phone',
};

export const GUARD_LABELS: Record<string, string> = {
    prompt_injection: 'agents.guardrails.guard_labels.prompt_injection',
    pii: 'agents.guardrails.guard_labels.pii',
    blocked_topic: 'agents.guardrails.guard_labels.blocked_topic',
    toxicity: 'agents.guardrails.guard_labels.toxicity',
    prompt_leak: 'agents.guardrails.guard_labels.prompt_leak',
};

export const OUTCOME_LABELS: Record<string, string> = {
    blocked: 'agents.guardrails.outcome_labels.blocked',
    redacted: 'agents.guardrails.outcome_labels.redacted',
    flagged: 'agents.guardrails.outcome_labels.flagged',
    skipped: 'agents.guardrails.outcome_labels.skipped',
};

export const STAGE_LABELS: Record<string, string> = {
    input: 'agents.guardrails.stage_labels.input',
    source: 'agents.guardrails.stage_labels.source',
    tool: 'agents.guardrails.stage_labels.tool',
    output: 'agents.guardrails.stage_labels.output',
};

export const MODE_LABELS: Record<string, string> = {
    block: 'agents.guardrails.mode_labels.block',
    monitor: 'agents.guardrails.mode_labels.monitor',
};

export const SENSITIVITY_LABELS: Record<string, string> = {
    low: 'agents.guardrails.sensitivity_labels.low',
    medium: 'agents.guardrails.sensitivity_labels.medium',
    high: 'agents.guardrails.sensitivity_labels.high',
};

export const PII_ACTION_LABELS: Record<string, string> = {
    mask: 'agents.guardrails.pii_action_labels.mask',
    block: 'agents.guardrails.pii_action_labels.block',
};

export const TOPIC_ACTION_LABELS: Record<string, string> = {
    refuse: 'agents.guardrails.topic_action_labels.refuse',
    reply: 'agents.guardrails.topic_action_labels.reply',
    handoff: 'agents.guardrails.topic_action_labels.handoff',
};

export const SEVERITY_LABELS: Record<string, string> = {
    none: 'agents.guardrails.severity_labels.none',
    low: 'agents.guardrails.severity_labels.low',
    medium: 'agents.guardrails.severity_labels.medium',
    high: 'agents.guardrails.severity_labels.high',
};

/** The translated label for `value` from `map`, or the value itself when the map has no key for it. */
export function labelFor(t: (key: string) => string, map: Record<string, string>, value: string | null | undefined): string {
    if (!value) return '—';

    return map[value] ? t(map[value]) : value;
}

export const ANSWER_CHECK_LABELS: Record<string, string> = {
    while_showing: 'agents.guardrails.answer_check_labels.while_showing',
    before_showing: 'agents.guardrails.answer_check_labels.before_showing',
    off: 'agents.guardrails.answer_check_labels.off',
};

export const ANSWER_CHECK_HELP: Record<string, string> = {
    while_showing: 'agents.guardrails.answer_check_help.while_showing',
    before_showing: 'agents.guardrails.answer_check_help.before_showing',
    off: 'agents.guardrails.answer_check_help.off',
};
