/**
 * Guardrails API: "Test a message" on an agent's Guardrails page, which runs
 * the input guards over a sample with the form's settings (saved or not).
 */
import { test } from '@/routes/agents/guardrails';
import { post } from './http';

/** One guard that fired on the sample. */
export interface GuardrailTestVerdict {
    guard_type: string;
    stage: string;
    outcome: string;
    severity: string | null;
    detail: Record<string, unknown>;
}

/** What the visitor would get for the sample, and which guards fired. */
export interface GuardrailTestResult {
    blocked: boolean;
    /** The message as the model would read it (personal data masked). */
    text: string;
    /** The refusal, topic reply or hand-off message shown instead of an answer. */
    reply: string | null;
    handoff: boolean;
    language: string | null;
    verdicts: GuardrailTestVerdict[];
}

export function testGuardrailMessage<S extends object>(agentId: string, settings: S, message: string): Promise<GuardrailTestResult> {
    return post<GuardrailTestResult>(test.url(agentId), { ...settings, message });
}
