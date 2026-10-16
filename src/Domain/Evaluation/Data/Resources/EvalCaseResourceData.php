<?php

namespace Domain\Evaluation\Data\Resources;

use Carbon\CarbonInterface;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One eval case as the Evals page lists and edits it.
 */
#[TypeScript]
class EvalCaseResourceData extends Data
{
    /**
     * @param  array<string, mixed>|null  $scorer_config
     */
    public function __construct(
        public int $id,
        public string $external_id,
        public string $name,
        public string $input,
        public ScorerType $scorer_type,
        public ?array $scorer_config,
        public ?string $expected_output,
        public bool $active,
        public CarbonInterface $created_at,
    ) {}

    public static function fromModel(EvalCase $case): self
    {
        return new self(
            id: $case->id,
            external_id: $case->external_id,
            name: $case->name,
            input: $case->input,
            scorer_type: $case->scorer_type,
            scorer_config: $case->scorer_config,
            expected_output: $case->expected_output,
            active: $case->is_active,
            created_at: $case->created_at,
        );
    }

    /**
     * @param  Collection<int, EvalCase>  $cases
     * @return Collection<int, EvalCaseResourceData>
     */
    public static function collection(Collection $cases): Collection
    {
        return $cases->map(fn (EvalCase $case): self => self::fromModel($case));
    }
}
