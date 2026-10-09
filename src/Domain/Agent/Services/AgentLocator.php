<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent as AgentModel;

/**
 * Stateless locator helpers for resolving an {@see AgentModel} from a
 * request (sub-domain or custom host) and minting collision-free external
 * ids. Named distinctly from the `Agent` model so call sites never have to
 * alias one of them away.
 */
class AgentLocator
{
    /**
     * The agent whose subdomain or external id is $subdomain. Callers pass a
     * non-empty slug: a NULL lookup would match agents without a subdomain.
     */
    public static function resolveAgentFromSubdomain(string $subdomain): ?AgentModel
    {
        return AgentModel::query()
            ->where('sub_domain', $subdomain)
            ->orWhere('external_id', $subdomain)
            ->first();
    }

    /**
     * Resolve a request host to an agent's `external_id` when the owner
     * still has Custom Domain quota. Returns null for unknown hosts,
     * agents whose owner has no quota (subscription cancelled, plan
     * downgraded), or hosts that just aren't bound to any agent.
     *
     * No caching: a single indexed lookup matches the existing
     * `resolveAgentFromSubdomain` pattern, and the quota check stays
     * authoritative without a TTL window where a cancelled subscription
     * keeps serving.
     */
    public static function resolveByHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        /** @var AgentModel|null $agent */
        $agent = AgentModel::query()
            ->where('domain', $host)
            ->with('user.quota')
            ->first();

        if (! $agent) {
            return null;
        }

        $owner = $agent->user;

        if (! $owner || $owner->totalCustomDomainsLimit() <= 0) {
            return null;
        }

        return $agent->external_id;
    }

    public static function generateRandomExternalId(int $len = 14): string
    {
        do {
            $externalId = substr(bin2hex(random_bytes((int) ceil($len / 2))), 0, $len);
        } while (AgentModel::where('external_id', $externalId)->exists());

        return $externalId;
    }
}
