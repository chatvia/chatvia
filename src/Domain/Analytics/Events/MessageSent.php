<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent
{
    use Dispatchable;

    /**
     * @param  'user'|'assistant'  $role
     */
    public function __construct(
        public readonly int $agentId,
        public readonly int $userId,
        public readonly string $conversationExternalId,
        public readonly string $messageExternalId,
        public readonly string $sessionExternalId,
        public readonly string $role,
        public readonly ?string $llmExternalName,
        public readonly ?string $countryCode,
        public readonly ?int $responseTimeMs,
        public readonly ?int $promptTokens,
        public readonly ?int $completionTokens,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
