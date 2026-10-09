<?php

declare(strict_types=1);

namespace App\Http\Resources\Handoff;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many visitor messages an operator just marked as read.
 */
#[TypeScript]
class HandoffMarkReadResultData extends Data
{
    public function __construct(
        public int $marked_count,
    ) {}
}
