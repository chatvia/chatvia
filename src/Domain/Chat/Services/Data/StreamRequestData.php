<?php

namespace Domain\Chat\Services\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

class StreamRequestData extends Data
{
    public function __construct(
        public Agent $agent,
        public StreamRequestMessageData $message,
        public string $externalSessionId,
        public string $conversationId,
        // In Chat, when the user creates a new conversation, their message will be saved with the conversation ID.
        public bool $saveUserMessageImmediately = false,
        public bool $streamSources = false,
        public bool $streamLanguage = false,
        public ?string $source = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
        public ?string $requestId = null,
    ) {}
}
