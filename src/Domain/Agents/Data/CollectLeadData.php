<?php

namespace Domain\Agents\Data;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

class CollectLeadData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $threadId,
        public string $sessionId,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $jobTitle = null,
        public ?string $company = null,
        public ?string $location = null,
        public ?string $message = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
        public ?string $ipAddress = null,
    ) {}
}
