<?php

namespace App\Http\Resources\Handoff;

use Domain\Conversation\Enums\HandoffStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response after an operator hands a conversation back to the AI.
 */
#[TypeScript]
class HandoffReturnResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public HandoffStatus $handoff_status,
    ) {}

    public static function returned(): self
    {
        return new self(
            success: true,
            message: __('Conversation has been returned to AI.'),
            handoff_status: HandoffStatus::Ai,
        );
    }
}
