<?php

namespace Domain\Conversation\Data\Handoff;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response payload after returning a conversation back to the AI.
 */
#[TypeScript]
class HandoffReturnResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public string $handoff_status,
    ) {}

    public static function ok(): self
    {
        return new self(
            success: true,
            message: 'Conversation has been returned to AI.',
            handoff_status: 'ai',
        );
    }
}
