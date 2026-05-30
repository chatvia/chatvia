<?php

namespace Domain\Chat\Data\Handoff;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response payload after the admin sends a reply into a handed-off conversation.
 */
#[TypeScript]
class HandoffMessageResultData extends Data
{
    public function __construct(
        public bool $success,
        public HandoffMessageResultPayloadData $message,
    ) {}
}
