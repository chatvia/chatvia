<?php

namespace Domain\Chat\Data;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

class CreateConversationData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $message,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
    ) {}
}
