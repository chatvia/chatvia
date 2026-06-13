<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent as AgentModel;
use Illuminate\Support\Facades\Cache;

/**
 * Stateless locator/cache helpers for resolving an {@see AgentModel} from a
 * request (sub-domain or custom host), caching agent names, and minting
 * collision-free external ids. Named distinctly from the `Agent` model so call
 * sites never have to alias one of them away.
 */
class AgentLocator
{
    public static function exists($name): bool
    {
        return Cache::has('agent-'.$name);
    }

    public static function missing($name): bool
    {
        return self::exists($name) === false;
    }

    public static function cacheAgentName($name): void
    {
        Cache::forever('agent-'.$name, '');
    }

    public static function resolveAgentFromSubdomain($subdomain): ?AgentModel
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

        $quota = $agent->user?->quota;

        if (! $quota || $quota->totalCustomDomainsLimit() <= 0) {
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
