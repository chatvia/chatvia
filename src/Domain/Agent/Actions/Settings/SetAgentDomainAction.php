<?php

namespace Domain\Agent\Actions\Settings;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\QueryBuilders\AgentQueryBuilder;
use Support\Exceptions\DomainValidationException;

/**
 * Set (or clear) an agent's custom domain. Clearing — or re-saving the same
 * value — is always allowed; setting a NEW domain is gated on the workspace's
 * Custom Domain quota (plan tier plus any active Custom Domain subscription).
 *
 * The quota count uses {@see AgentQueryBuilder::withCustomDomain()},
 * the same predicate the Setup page renders, so the gate and the displayed
 * "X of Y used" figure can never disagree.
 *
 * @throws DomainValidationException when the workspace has no custom-domain
 *                                   capacity, or has already used it all.
 */
class SetAgentDomainAction
{
    public function execute(Agent $agent, User $workspace, ?string $domain): void
    {
        if ($domain !== null && $domain !== $agent->domain) {
            $total = $workspace->totalCustomDomainsLimit();

            if ($total === 0) {
                // Plan names change; say what is missing, not which plan has it.
                throw new DomainValidationException('domain', __('Custom domains are not included in your plan. Upgrade your plan or add a Custom Domain subscription.'));
            }

            $used = (int) $workspace->agents()
                ->withCustomDomain()
                ->where('id', '!=', $agent->id)
                ->count();

            if ($used >= $total) {
                throw new DomainValidationException('domain', __('You have used all your custom domains. Increase your Custom Domain subscription to add another.'));
            }
        }

        $agent->update(['domain' => $domain]);
    }
}
