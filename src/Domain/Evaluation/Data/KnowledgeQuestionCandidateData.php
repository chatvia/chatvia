<?php

namespace Domain\Evaluation\Data;

use Domain\Evaluation\Enums\EvalQuestionSource;
use Spatie\LaravelData\Data;

/**
 * One question the "From knowledge" picker offers: where it came from (a QA
 * pair or a mined chunk) and its id there, a label for its source, and whether
 * it is already an eval case. `key` is unique within one listing, for the UI.
 */
class KnowledgeQuestionCandidateData extends Data
{
    public function __construct(
        public string $key,
        public EvalQuestionSource $type,
        public int $id,
        public string $question,
        public string $source,
        public bool $already_added,
    ) {}
}
