<?php

namespace App\Http\Resources\Handoff;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One conversation waiting for a human operator, as the dashboard's handoff
 * list shows it. Identifiers are the public uuids, never the internal keys.
 */
#[TypeScript]
class HandoffSummaryData extends Data
{
    private const string ANONYMOUS_NAME = 'Visitor';

    public function __construct(
        public string $conversation_id,
        public string $user_name,
        public ?string $user_email,
        public ?string $last_message,
        public ?string $last_message_at,
        public string $requested_at,
        public string $session_id,
    ) {}

    public static function fromConversation(Conversation $conversation): self
    {
        return new self(
            conversation_id: $conversation->external_id,
            user_name: $conversation->displayName() ?? self::ANONYMOUS_NAME,
            user_email: $conversation->displayEmail(),
            last_message: $conversation->lastMessage?->content,
            last_message_at: $conversation->lastMessage?->created_at?->toIso8601String(),
            requested_at: $conversation->updated_at->toIso8601String(),
            session_id: $conversation->session->external_id,
        );
    }
}
