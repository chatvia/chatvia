<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\Account;

use Carbon\CarbonInterface;
use Domain\Agent\Enums\AgentDomainStatus;
use Domain\Agent\Enums\DomainCheck;
use Domain\Agent\Models\AgentDomain;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An agent's custom domain as the Deploy page shows it: the domain, where it
 * stands and what the last check found.
 */
#[TypeScript]
class AgentDomainResourceData extends Data
{
    public function __construct(
        public string $host,
        public AgentDomainStatus $status,
        public ?DomainCheck $last_check,
        public ?CarbonInterface $last_checked_at,
    ) {}

    public static function fromModel(AgentDomain $domain): self
    {
        return new self(
            host: $domain->host,
            status: $domain->status(),
            last_check: $domain->last_check,
            last_checked_at: $domain->last_checked_at,
        );
    }
}
