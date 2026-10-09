<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Pulse;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Conversation-level vitals shown alongside the pulse — totals, overall
 * sentiment/tone, and the count of visitor messages that fed the calculation.
 */
#[TypeScript]
class ChatLogPulseConversationData extends Data
{
    public function __construct(
        public string $id,
        public ?string $sentiment,
        public ?string $tone,
        public int $total_messages,
        public int $user_messages,
    ) {}
}
