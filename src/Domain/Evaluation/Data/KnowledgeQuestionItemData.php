<?php

declare(strict_types=1);

namespace Domain\Evaluation\Data;

use Domain\Evaluation\Enums\EvalQuestionSource;
use Spatie\LaravelData\Data;

/**
 * One question the user picked in the "From knowledge" dialog. `id` points at
 * the owning chunk or QA pair — the reference answer is re-fetched from it
 * server-side, never trusted from the client.
 */
class KnowledgeQuestionItemData extends Data
{
    public function __construct(
        public EvalQuestionSource $type,
        public int $id,
        public string $question,
    ) {}
}
