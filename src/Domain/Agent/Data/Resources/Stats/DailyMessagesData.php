<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * AI replies sent on one day of the selected range.
 */
#[TypeScript]
class DailyMessagesData extends Data
{
    public function __construct(
        public string $date,
        public int $messages,
    ) {}
}
