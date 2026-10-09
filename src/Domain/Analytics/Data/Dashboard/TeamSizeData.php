<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * How many members the current team has (the owner not counted).
 */
class TeamSizeData extends Data
{
    public function __construct(
        public int $members,
    ) {}
}
