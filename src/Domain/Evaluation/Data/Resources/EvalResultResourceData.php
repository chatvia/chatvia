<?php

namespace Domain\Evaluation\Data\Resources;

use Carbon\CarbonInterface;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalResult;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One scored case of an eval run, as the run drill-down shows it: pass/score,
 * the agent's (possibly truncated) answer, the judge's rationale, and the
 * sources and tools the agent used.
 */
#[TypeScript]
class EvalResultResourceData extends Data
{
    /**
     * @param  list<string>  $sources_used
     * @param  list<string>  $tools_called
     */
    public function __construct(
        public int $id,
        public string $external_id,
        public ?int $case_id,
        public string $case_name,
        public ScorerType $scorer_type,
        public bool $pass,
        public ?float $score,
        public ?string $actual_output,
        public bool $actual_output_truncated,
        public array $sources_used,
        public array $tools_called,
        public ?string $judge_rationale,
        public ?int $latency_ms,
        public ?int $tokens,
        public ?string $trace_id,
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(EvalResult $result): self
    {
        return new self(
            id: $result->id,
            external_id: $result->external_id,
            case_id: $result->case_id,
            case_name: $result->case_name,
            scorer_type: $result->scorer_type,
            pass: $result->has_passed,
            score: $result->score,
            actual_output: $result->actual_output,
            actual_output_truncated: $result->actual_output_truncated,
            sources_used: $result->sources_used ?? [],
            tools_called: $result->tools_called ?? [],
            judge_rationale: $result->judge_rationale,
            latency_ms: $result->latency_ms,
            tokens: $result->tokens,
            trace_id: $result->trace_id,
            created_at: $result->created_at,
        );
    }

    /**
     * @param  Collection<int, EvalResult>  $results
     * @return Collection<int, EvalResultResourceData>
     */
    public static function collection(Collection $results): Collection
    {
        return $results->map(fn (EvalResult $result): self => self::fromModel($result));
    }
}
