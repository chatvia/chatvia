<?php

declare(strict_types=1);

namespace App\Http\Resources\Handoff;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Acknowledges an operator's typing indicator.
 */
#[TypeScript]
class HandoffTypingResultData extends Data
{
    public function __construct(
        public bool $success = true,
    ) {}
}
