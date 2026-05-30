<?php

namespace Domain\Chat\Data\Handoff;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HandoffTypingResultData extends Data
{
    public function __construct(
        public bool $success = true,
    ) {}
}
