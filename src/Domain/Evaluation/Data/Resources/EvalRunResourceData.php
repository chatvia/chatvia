<?php

namespace Domain\Evaluation\Data\Resources;

use Carbon\CarbonInterface;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EvalRunResourceData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $status,
        public int $total_cases,
        public int $passed_count,
        public int $failed_count,
        public ?float $avg_score,
        public ?int $pass_rate,
        public ?int $total_tokens,
        public ?string $model_snapshot,
        public ?string $triggered_by_name,
        public ?string $triggered_by_avatar,
        public ?CarbonInterface $started_at,
        public ?CarbonInterface $finished_at,
        public CarbonInterface $created_at,
    ) {}

    public static function fromModel(EvalRun $run): self
    {
        $scored = $run->passed_count + $run->failed_count;

        return new self(
            id: $run->id,
            external_id: $run->external_id,
            status: $run->status,
            total_cases: $run->total_cases,
            passed_count: $run->passed_count,
            failed_count: $run->failed_count,
            avg_score: $run->avg_score,
            pass_rate: $scored > 0 ? (int) round($run->passed_count / $scored * 100) : null,
            total_tokens: $run->total_tokens,
            model_snapshot: $run->model_snapshot,
            triggered_by_name: $run->triggered_by_name,
            // Only when eager-loaded — under Model::shouldBeStrict() a lazy load
            // would throw, and this resource is built on the broadcast path too.
            triggered_by_avatar: $run->relationLoaded('triggeredByUser') ? $run->triggeredByUser?->avatar : null,
            started_at: $run->started_at,
            finished_at: $run->finished_at,
            created_at: $run->created_at,
        );
    }

    /**
     * @param  Collection<int, EvalRun>  $runs
     * @return Collection<int, EvalRunResourceData>
     */
    public static function collection(Collection $runs): Collection
    {
        return $runs->map(fn (EvalRun $run): self => self::fromModel($run));
    }
}
