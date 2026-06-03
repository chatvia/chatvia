<?php

namespace Domain\Conversation\Data\Handoff;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response payload after an admin takes over a conversation: the conversation
 * is now in `human` handoff status pinned to the acting admin.
 */
#[TypeScript]
class HandoffTakeoverResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public string $handoff_status,
        public ?string $user_name,
        public ?string $user_email,
    ) {}

    public static function ok(Conversation $conversation): self
    {
        return new self(
            success: true,
            message: 'You have taken over this conversation.',
            handoff_status: 'human',
            user_name: $conversation->displayName(),
            user_email: $conversation->displayEmail(),
        );
    }
}
