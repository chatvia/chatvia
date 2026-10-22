<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Agent;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One day of the overview message chart (`Y-m-d` plus that day's reply count).
 */
#[TypeScript]
class DailyMessagePointData extends Data
{
    public function __construct(
        public string $date,
        public int $messages,
    ) {}
}
