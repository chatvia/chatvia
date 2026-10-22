<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Handoff;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * A visitor's message to the operator holding the conversation, with the
 * request facts stored on the session.
 */
class SendVisitorHandoffMessageData extends Data
{
    public function __construct(
        public Conversation $conversation,
        public string $content,
        public ?int $llmId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
    ) {}
}
