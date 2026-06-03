<?php

namespace Domain\Conversation\Data\Messages;

use Spatie\LaravelData\Data;

class BroadcastChatTypingData extends Data
{
    public function __construct(
        public string $conversationId,
        public bool $isTyping,
        public string $typerType,
        public ?string $typerName = null,
        public ?string $typerId = null,
    ) {}
}
