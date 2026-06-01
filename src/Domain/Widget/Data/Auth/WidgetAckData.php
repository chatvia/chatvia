<?php

namespace Domain\Widget\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Generic "done" envelope returned by side-effect widget endpoints
 * (feedback, typing, mark-read, …).
 */
#[TypeScript]
class WidgetAckData extends Data
{
    public function __construct(
        public bool $success = true,
    ) {}
}
