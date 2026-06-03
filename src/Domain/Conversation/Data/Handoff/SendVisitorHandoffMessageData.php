<?php

namespace Domain\Conversation\Data\Handoff;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

class SendVisitorHandoffMessageData extends Data
{
    public function __construct(
        public Conversation $conversation,
        public string $content,
        public ?string $model = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
    ) {}
}
