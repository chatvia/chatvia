<?php

namespace App\Http\Resources\Handoff;

use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Compact projection of the operator message just added to a handed-off
 * conversation — what the page renders inline without a refresh.
 */
#[TypeScript]
class HandoffMessageResultPayloadData extends Data
{
    public function __construct(
        /** The message's public id (external_id); internal keys never leave the server. */
        public string $id,
        public string $content,
        public MessageRole $role,
        public string $created_at,
    ) {}

    public static function fromMessage(Message $message): self
    {
        return new self(
            id: $message->external_id,
            content: (string) $message->content,
            role: $message->role,
            created_at: $message->created_at->toIso8601String(),
        );
    }
}
