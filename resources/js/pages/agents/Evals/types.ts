/**
 * Shapes shared by the Evals pages (suite list + single run) and their child
 * components. Runs and results reuse the generated PHP resource types; a case
 * narrows the server's free-form `scorer_config` to the keys the scorers read.
 */

/** The per-scorer settings stored on a case. Each scorer reads only its own keys. */
export interface ScorerConfig {
    /** `contains`: the substring the answer must include. */
    value?: string;
    /** `contains`: match case exactly when true. */
    case_sensitive?: boolean;
    /** `regex`: the pattern the answer must match. */
    pattern?: string;
    /** `llm_judge`: what the judge model grades the answer against. */
    criteria?: string;
    /** `json_schema`: keys the JSON answer must contain. */
    required?: string[];
    /** `tool_call`: the tool the agent is expected to call. */
    tool?: string;
    /** `tool_call`: expected arguments — a raw JSON string from the form, or decoded JSON on older rows. */
    arguments?: unknown;
}

export type EvalCase = Omit<Domain.Evaluation.Data.Resources.EvalCaseResourceData, 'scorer_config'> & {
    scorer_config: ScorerConfig | null;
};

export type EvalRun = Domain.Evaluation.Data.Resources.EvalRunResourceData;

export type EvalResult = Domain.Evaluation.Data.Resources.EvalResultResourceData;

/** A question mined from the agent's knowledge base that can become an eval case. */
export interface KnowledgeQuestion {
    key: string;
    type: 'chunk' | 'qa';
    id: number;
    question: string;
    source: string;
    already_added: boolean;
}

/** The minimal agent shape the Evals pages need. */
export interface EvalAgent {
    external_id: string;
    name: string;
}

/** Reverb payload: a run row was started, updated or finalized. */
export interface EvalRunProgressedEvent {
    run: EvalRun;
}

/** Reverb payload: one case of a run was scored. */
export interface EvalCaseScoredEvent {
    run_external_id: string;
    result: EvalResult;
    passed_count: number;
    failed_count: number;
}

export type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

/** Badge variant for a run status: primary when completed, destructive when failed, neutral while running. */
export const runStatusVariant = (status: string): BadgeVariant => {
    if (status === 'completed') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
};
