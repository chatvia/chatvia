<?php

namespace Domain\Agents\Data\Resources\ChatLog\Handoff;

use Domain\Chat\Models\ChatConversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Current handoff state for a chat-log conversation — drives whether the UI
 * shows the takeover, return, or "in handoff" affordance.
 */
#[TypeScript]
class ChatLogHandoffStatusData extends Data
{
    public function __construct(
        public string $status,
        public ?int $admin_id,
        public ?string $admin_name,
        public ?string $handoff_at,
    ) {}

    public static function fromConversation(ChatConversation $conversation): self
    {
        $conversation->loadMissing('handoffUser');

        return new self(
            status: $conversation->handoff_status ?? 'ai',
            admin_id: $conversation->handoff_user_id,
            admin_name: $conversation->handoffUser?->name,
            handoff_at: $conversation->handoff_at?->toIso8601String(),
        );
    }
}
