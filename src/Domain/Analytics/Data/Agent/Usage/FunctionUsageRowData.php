<?php

namespace Domain\Analytics\Data\Agent\Usage;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One webhook function on the dashboard's source-usage analytics page, with
 * its call count and last use.
 */
#[TypeScript]
class FunctionUsageRowData extends Data
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public int $count,
        public ?string $last_used_at,
    ) {}

    public static function fromUsage(FunctionUsageData $function): self
    {
        return new self(
            name: $function->name,
            description: $function->description,
            count: $function->calls,
            // The rollup timestamps come back from DB::table without a tz
            // suffix; stamp them as UTC so the browser parses them correctly.
            last_used_at: $function->lastUsedOn === null ? null : Carbon::parse($function->lastUsedOn, 'UTC')->toIso8601ZuluString(),
        );
    }
}
