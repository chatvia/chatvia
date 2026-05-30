<?php

namespace Domain\Chat\Data\Handoff;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HandoffMarkReadResultData extends Data
{
    public function __construct(
        public int $marked_count,
    ) {}
}
