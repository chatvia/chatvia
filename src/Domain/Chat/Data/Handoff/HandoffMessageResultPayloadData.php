<?php

namespace Domain\Chat\Data\Handoff;

use Domain\Chat\Models\ChatMessage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Compact projection of the admin message just appended to a handed-off
 * conversation — what the page renders inline without a refresh.
 */
#[TypeScript]
class HandoffMessageResultPayloadData extends Data
{
    public function __construct(
        public int $id,
        public string $content,
        public string $role,
        public string $created_at,
    ) {}

    public static function fromMessage(ChatMessage $message): self
    {
        return new self(
            id: $message->id,
            content: $message->content,
            role: $message->role,
            created_at: $message->created_at->toIso8601String(),
        );
    }
}
