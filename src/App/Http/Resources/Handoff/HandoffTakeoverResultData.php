<?php

namespace App\Http\Resources\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response after an operator takes a conversation over: it is now with a
 * human (the acting operator), and the panel shows who the visitor is. The
 * holder's id and name let the dashboard tell its own takeover from a
 * colleague's.
 */
#[TypeScript]
class HandoffTakeoverResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public HandoffStatus $handoff_status,
        public int $admin_id,
        public string $admin_name,
        public ?string $user_name,
        public ?string $user_email,
    ) {}

    public static function fromConversation(Conversation $conversation, User $admin): self
    {
        return new self(
            success: true,
            message: __('You have taken over this conversation.'),
            handoff_status: HandoffStatus::Human,
            admin_id: $admin->id,
            admin_name: $admin->name,
            user_name: $conversation->displayName(),
            user_email: $conversation->displayEmail(),
        );
    }
}
