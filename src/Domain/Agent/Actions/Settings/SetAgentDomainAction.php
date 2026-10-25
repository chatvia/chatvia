<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\CustomDomain\CustomDomainVerification;

/**
 * Sets (or clears, with null) the custom domain of an agent's Hosted Chat. A
 * new domain is only pending: it shows the chat once
 * {@see VerifyAgentDomainAction} finds the agent's TXT record on it, and the
 * domain in use until then stays in use. The format rules live in
 * UpdateAgentDomainRequest.
 */
class SetAgentDomainAction
{
    public function execute(Agent $agent, ?string $domain): void
    {
        if ($domain === null) {
            $agent->update(['domain' => null, 'pending_domain' => null]);

            return;
        }

        if ($domain === $agent->domain) {
            $agent->update(['pending_domain' => null]);

            return;
        }

        $agent->update([
            'pending_domain' => $domain,
            'domain_verification_token' => $agent->domain_verification_token ?? CustomDomainVerification::newToken(),
        ]);
    }
}
