<?php

namespace Domain\Evaluation\Data;

use Spatie\LaravelData\Data;

/**
 * The questions selected in the Evals "From knowledge" picker, built from the
 * CreateEvalCasesFromKnowledgeRequest. The owning agent comes from the route.
 */
class CreateEvalCasesFromQuestionsData extends Data
{
    /**
     * @param  list<KnowledgeQuestionItemData>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
