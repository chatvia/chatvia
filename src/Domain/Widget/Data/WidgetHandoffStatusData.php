<?php

namespace Domain\Widget\Data;

use Domain\Chat\Models\ChatConversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetHandoffStatusData extends Data
{
    public function __construct(
        public string $conversation_id,
        public string $handoff_status,
        public bool $is_human_controlled,
        public ?string $admin_name,
    ) {}

    public static function unhandedOff(string $conversationId): self
    {
        return new self(
            conversation_id: $conversationId,
            handoff_status: 'ai',
            is_human_controlled: false,
            admin_name: null,
        );
    }

    public static function fromConversation(ChatConversation $conversation, string $conversationId): self
    {
        $conversation->loadMissing('handoffUser');
        $handedOff = $conversation->isHandedOff();

        return new self(
            conversation_id: $conversationId,
            handoff_status: $conversation->handoff_status ?? 'ai',
            is_human_controlled: $handedOff,
            admin_name: $handedOff ? $conversation->handoffUser?->name : null,
        );
    }
}
