<?php

declare(strict_types=1);

namespace App\Console\Commands\Agent;

use Domain\Agent\Jobs\VerifyAgentDomainJob;
use Domain\Agent\Models\AgentDomain;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled checks of agents' custom domains: a new domain until it verifies
 * (for its first two days), and every verified domain once a day, so one
 * that no longer points here stops serving the chat.
 */
#[Description('Check the custom domains of agents that are due: new ones every ten minutes for two days, verified ones daily. Does nothing while custom domains are off or the install has no license key.')]
#[Signature('chatvia:check:agent-domains')]
class CheckAgentDomains extends Command
{
    public function handle(CustomDomainLookup $domains): int
    {
        if (! $domains->isAvailable()) {
            return self::SUCCESS;
        }

        $queued = 0;

        AgentDomain::query()->dueForCheck()->lazyById()->each(function (AgentDomain $domain) use (&$queued): void {
            VerifyAgentDomainJob::dispatch($domain);
            $queued++;
        });

        $this->info("Queued {$queued} custom domain check(s).");

        return self::SUCCESS;
    }
}
