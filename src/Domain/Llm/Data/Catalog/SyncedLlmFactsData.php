<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Catalog;

use Spatie\LaravelData\Data;

/**
 * What one sync of the models' facts did: how many models got the catalog's
 * facts, and how many the catalog does not list.
 */
final class SyncedLlmFactsData extends Data
{
    public function __construct(
        public int $updated,
        public int $notListed,
    ) {}
}
