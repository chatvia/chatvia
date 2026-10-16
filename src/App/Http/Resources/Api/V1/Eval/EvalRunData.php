<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Eval;

use Carbon\CarbonInterface;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalRun;
use Spatie\LaravelData\Data;

/**
 * One eval-suite run over the public API: its status, counts and pass rate —
 * what a CI job polls after triggering a run. `results` (the per-case scores)
 * is filled on the single-run endpoint and null on the list.
 */
class EvalRunData extends Data
{
    public function __construct(
        /**
         * The run's ID.
         */
        public string $id,
        /**
         * `running` while cases are being scored, then `completed` or `failed`.
         */
        public EvalRunStatus $status,
        /**
         * How many cases the run scores in total.
         */
        public int $total_cases,
        /**
         * Cases scored as passing so far.
         */
        public int $passed_count,
        /**
         * Cases scored as failing so far.
         */
        public int $failed_count,
        /**
         * Mean score over the scored cases (0–1), when scores exist.
         */
        public ?float $avg_score,
        /**
         * Passed share of the scored cases, in whole percent.
         */
        public ?int $pass_rate,
        /**
         * Tokens the run's agent calls used in total.
         */
        public ?int $total_tokens,
        /**
         * The model the agent ran on when the run started.
         */
        public ?string $model_snapshot,
        /**
         * When the run started scoring.
         */
        public ?CarbonInterface $started_at,
        /**
         * When the run finished; null while it is still running.
         */
        public ?CarbonInterface $finished_at,
        /**
         * When the run was created.
         */
        public ?CarbonInterface $created_at,
        /**
         * The per-case results, on the single-run endpoint only.
         *
         * @var list<EvalResultData>|null
         */
        public ?array $results = null,
    ) {}

    /**
     * @param  list<EvalResultData>|null  $results
     */
    public static function fromModel(EvalRun $run, ?array $results = null): self
    {
        $scored = $run->passed_count + $run->failed_count;

        return new self(
            id: $run->external_id,
            status: $run->status,
            total_cases: $run->total_cases,
            passed_count: $run->passed_count,
            failed_count: $run->failed_count,
            avg_score: $run->avg_score,
            pass_rate: $scored > 0 ? (int) round($run->passed_count / $scored * 100) : null,
            total_tokens: $run->total_tokens,
            model_snapshot: $run->model_snapshot,
            started_at: $run->started_at,
            finished_at: $run->finished_at,
            created_at: $run->created_at,
            results: $results,
        );
    }
}
