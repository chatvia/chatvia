<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Topics;

use Spatie\LaravelData\Data;

/**
 * An emergent topic the analysis keeps suggesting for an agent: its label, in
 * how many conversations it came up, and a few of those conversations.
 */
class SuggestedTopicData extends Data
{
    /**
     * @param  list<SuggestedTopicExampleData>  $examples
     */
    public function __construct(
        public string $label,
        public int $count,
        /** @var list<SuggestedTopicExampleData> */
        public array $examples,
    ) {}
}
