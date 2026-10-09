<?php

namespace App\Http\Resources\Handoff;

use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response after an operator takes a conversation over: it is now with a
 * human (the acting operator), and the panel shows who the visitor is.
 */
#[TypeScript]
class HandoffTakeoverResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public HandoffStatus $handoff_status,
        public ?string $user_name,
        public ?string $user_email,
    ) {}

    public static function fromConversation(Conversation $conversation): self
    {
        return new self(
            success: true,
            message: __('You have taken over this conversation.'),
            handoff_status: HandoffStatus::Human,
            user_name: $conversation->displayName(),
            user_email: $conversation->displayEmail(),
        );
    }
}
