<?php

declare(strict_types=1);

namespace Domain\Evaluation\Data\Resources;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many of an agent's knowledge sources its last finished eval run drew
 * on, and the names of the ones none of the cases reached, so staff can add
 * a case for each. A source no eval tests can break without anyone noticing.
 */
#[TypeScript]
class EvalSourceCoverageData extends Data
{
    /**
     * @param  list<string>  $untested  the names of the sources no case reached, at most a page's worth
     */
    public function __construct(
        public int $total,
        public int $tested,
        public array $untested,
    ) {}
}
