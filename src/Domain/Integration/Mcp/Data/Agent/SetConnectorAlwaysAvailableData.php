<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data\Agent;

use Spatie\LaravelData\Data;

/**
 * Whether an attached connector's tools should ship on every turn of the
 * agent ("always on"), bypassing the relevance router.
 */
class SetConnectorAlwaysAvailableData extends Data
{
    public function __construct(
        public bool $always_available,
    ) {}
}
