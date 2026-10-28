<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Domain;

use Domain\Agent\Enums\DomainCheck;
use Domain\Agent\Models\AgentDomain;
use Domain\Agent\Services\CustomDomainProbe;

/**
 * Checks an agent's custom domain and records what was found. The first
 * check that reaches this install verifies the domain, so it starts serving
 * the chat. A verified domain keeps serving through a failed check or two
 * (a short outage, a DNS change in flight) and stops after
 * {@see AgentDomain::FAILED_CHECKS_TO_UNVERIFY} failures in a row.
 */
final readonly class VerifyAgentDomainAction
{
    public function __construct(private CustomDomainProbe $probe) {}

    public function execute(AgentDomain $domain): AgentDomain
    {
        $check = $this->probe->check($domain->host, $domain->verification_token);
        $isReached = $check === DomainCheck::Reached;
        $failedChecks = $isReached ? 0 : $domain->failed_checks + 1;

        $domain->forceFill([
            'last_check' => $check,
            'last_checked_at' => now(),
            'failed_checks' => $failedChecks,
            'verified_at' => match (true) {
                $isReached => $domain->verified_at ?? now(),
                $failedChecks >= AgentDomain::FAILED_CHECKS_TO_UNVERIFY => null,
                default => $domain->verified_at,
            },
        ])->save();

        return $domain;
    }
}
