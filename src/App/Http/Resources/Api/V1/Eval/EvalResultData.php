<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Eval;

use Carbon\CarbonInterface;
use Domain\Evaluation\Models\EvalResult;
use Spatie\LaravelData\Data;

/**
 * One scored case of an eval run over the public API: pass/score, the agent's
 * (possibly truncated) answer and what the agent used to produce it.
 */
class EvalResultData extends Data
{
    public function __construct(
        /**
         * The result's ID.
         */
        public string $id,
        /**
         * The case's name when it was scored.
         */
        public string $case_name,
        /**
         * How the case was scored (`contains`, `exact_match`, `llm_judge`…).
         */
        public string $scorer_type,
        /**
         * Whether the case passed.
         */
        public bool $pass,
        /**
         * The scorer's score for the case (0–1), when it produces one.
         */
        public ?float $score,
        /**
         * The agent's answer, possibly truncated for storage.
         */
        public ?string $actual_output,
        /**
         * Whether `actual_output` was truncated.
         */
        public bool $actual_output_truncated,
        /**
         * The knowledge sources the agent retrieved for the answer.
         *
         * @var list<string>
         */
        public array $sources_used,
        /**
         * The tools the agent called while answering.
         *
         * @var list<string>
         */
        public array $tools_called,
        /**
         * The LLM judge's reasoning, for judge-scored cases.
         */
        public ?string $judge_rationale,
        /**
         * How long the agent took to answer, in milliseconds.
         */
        public ?int $latency_ms,
        /**
         * Tokens the agent call used.
         */
        public ?int $tokens,
        /**
         * When the case was scored.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(EvalResult $result): self
    {
        return new self(
            id: $result->external_id,
            case_name: $result->case_name,
            scorer_type: $result->scorer_type,
            pass: $result->pass,
            score: $result->score,
            actual_output: $result->actual_output,
            actual_output_truncated: $result->actual_output_truncated,
            sources_used: $result->sources_used ?? [],
            tools_called: $result->tools_called ?? [],
            judge_rationale: $result->judge_rationale,
            latency_ms: $result->latency_ms,
            tokens: $result->tokens,
            created_at: $result->created_at,
        );
    }
}
