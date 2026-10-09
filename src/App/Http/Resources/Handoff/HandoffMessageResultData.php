<?php

namespace App\Http\Resources\Handoff;

use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response after an operator sends a reply into a conversation they hold.
 */
#[TypeScript]
class HandoffMessageResultData extends Data
{
    public function __construct(
        public bool $success,
        public HandoffMessageResultPayloadData $message,
    ) {}

    public static function fromMessage(Message $message): self
    {
        return new self(success: true, message: HandoffMessageResultPayloadData::fromMessage($message));
    }
}
