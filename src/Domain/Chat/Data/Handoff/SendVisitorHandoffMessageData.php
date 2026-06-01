<?php

namespace Domain\Chat\Data\Handoff;

use Domain\Chat\Models\ChatConversation;
use Spatie\LaravelData\Data;

class SendVisitorHandoffMessageData extends Data
{
    public function __construct(
        public ChatConversation $conversation,
        public string $content,
        public ?string $model = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
    ) {}
}
