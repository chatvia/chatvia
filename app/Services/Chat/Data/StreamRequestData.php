<?php

namespace App\Services\Chat\Data;

use App\Models\Agent;
use Spatie\LaravelData\Data;

class StreamRequestData extends Data
{
    public function __construct(
        public Agent $agent,
        public StreamRequestMessageData $message,
        public string $sessionId,
        public string $conversationId,
        // In Chat, when the user creates a new conversation, their message will be saved with the conversation ID.
        public bool $saveUserMessageImmediately = false,
        public bool $persistMessage = true,
        public bool $streamSources = true,
        public bool $streamLanguage = false,
    ) {}
}
