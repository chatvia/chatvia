<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Domain\Conversation\Services\Turn\KnowledgeEnricher;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * What the knowledge holds for one question asked during a voice call
 * ({@see KnowledgeEnricher::lookup()}): a curated Q&A answer, or the chunks
 * that passed the relevance and injection checks, best first. Both empty
 * means nothing matched.
 */
class KnowledgeLookupData extends Data
{
    /**
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    public function __construct(
        public ?QaMatchData $qaMatch,
        public Collection $sources,
    ) {}
}
