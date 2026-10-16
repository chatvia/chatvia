<?php

declare(strict_types=1);

namespace Domain\Form\Data;

use Spatie\LaravelData\Data;

/**
 * The submissions a bulk action applies to, by external id. An empty
 * selection means "all" for an export and "none" for a delete.
 */
class SubmissionSelectionData extends Data
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(
        public array $ids,
    ) {}
}
