<?php

namespace Domain\Widget\Data\Handoff;

use Domain\Conversation\Enums\HandoffStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Answer to a widget visitor's "talk to a human" request: the conversation's
 * handoff status after the request, with a short explanation.
 */
#[TypeScript]
class WidgetHandoffRequestResultData extends Data
{
    public function __construct(
        public string $status,
        public string $conversation_id,
        public string $message,
    ) {}

    public static function make(HandoffStatus|string $status, string $conversationId): self
    {
        $status = $status instanceof HandoffStatus ? $status->value : $status;

        return new self(
            status: $status,
            conversation_id: $conversationId,
            message: match ($status) {
                HandoffStatus::Human->value => 'Already connected to a human agent.',
                HandoffStatus::Pending->value => 'Your request has been sent. Please wait for an agent to connect.',
                default => 'Your request is pending. Please wait for an agent.',
            },
        );
    }
}
