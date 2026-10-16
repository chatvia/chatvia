<?php

namespace Domain\Widget\Data\Handoff;

use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who answers a widget conversation right now — the AI or a human operator —
 * polled by the widget to switch its live-chat UI on and off.
 */
#[TypeScript]
class WidgetHandoffStatusData extends Data
{
    public function __construct(
        public string $conversation_id,
        public HandoffStatus $handoff_status,
        public bool $is_human_controlled,
        public ?string $admin_name,
    ) {}

    /** Status for a conversation the visitor cannot see: the AI answers. */
    public static function unhandedOff(string $conversationId): self
    {
        return new self(
            conversation_id: $conversationId,
            handoff_status: HandoffStatus::Ai,
            is_human_controlled: false,
            admin_name: null,
        );
    }

    public static function fromConversation(Conversation $conversation, string $conversationId): self
    {
        $conversation->loadMissing('handoffUser');
        $handedOff = $conversation->isHandedOff();

        return new self(
            conversation_id: $conversationId,
            handoff_status: $conversation->handoff_status ?? HandoffStatus::Ai,
            is_human_controlled: $handedOff,
            admin_name: $handedOff ? $conversation->handoffUser?->name : null,
        );
    }
}
