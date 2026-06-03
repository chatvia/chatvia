<?php

namespace Domain\Billing\Data\Resources\Quota;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingQuotaFixedData extends Data
{
    public function __construct(
        public int $agents_limit = 0,
        public int $pages_limit = 0,
        public int $minutes_limit = 0,
        public int $links_limit = 0,
        public int $custom_domains_limit = 0,
        public int $workspace_users_limit = 0,
    ) {}

    public static function fromArray(array $fixed): self
    {
        return new self(
            agents_limit: $fixed['agents_limit'] ?? 0,
            pages_limit: $fixed['pages_limit'] ?? 0,
            minutes_limit: $fixed['minutes_limit'] ?? 0,
            links_limit: $fixed['links_limit'] ?? 0,
            custom_domains_limit: $fixed['custom_domains_limit'] ?? 0,
            workspace_users_limit: $fixed['workspace_users_limit'] ?? 0,
        );
    }
}
