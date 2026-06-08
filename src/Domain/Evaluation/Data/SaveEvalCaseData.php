<?php

namespace Domain\Evaluation\Data;

use Domain\Evaluation\Enums\ScorerType;
use Spatie\LaravelData\Data;

/**
 * The editable fields of an eval case, built from the SaveEvalCaseRequest form
 * request. The owning agent + workspace are derived from the route, not this DTO.
 */
class SaveEvalCaseData extends Data
{
    /**
     * @param  array<string, mixed>|null  $scorer_config
     */
    public function __construct(
        public string $name,
        public string $input,
        public ScorerType $scorer_type,
        public ?array $scorer_config,
        public ?string $expected_output,
        public bool $active,
    ) {}
}
