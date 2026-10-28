<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Domain;

use Domain\Agent\Data\Domain\SaveAgentDomainData;
use Domain\Agent\Jobs\VerifyAgentDomainJob;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Support\Str;

/**
 * Saves the custom domain of an agent's Hosted Chat and checks it at once.
 * A new domain starts unverified with a new check token, so it serves the
 * chat only after a check reaches this install through it; saving the same
 * domain again keeps it as it is.
 */
final class SaveAgentDomainAction
{
    public function execute(SaveAgentDomainData $data): AgentDomain
    {
        $domain = AgentDomain::query()->firstOrNew(['agent_id' => $data->agent->id]);

        if ($domain->host !== $data->host) {
            $domain->forceFill([
                'host' => $data->host,
                'verification_token' => Str::random(40),
                'verified_at' => null,
                'last_checked_at' => null,
                'last_check' => null,
                'failed_checks' => 0,
            ])->save();
        }

        VerifyAgentDomainJob::dispatch($domain)->afterCommit();

        return $domain;
    }
}
