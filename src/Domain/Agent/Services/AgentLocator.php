<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent as AgentModel;
use Illuminate\Support\Str;

/**
 * Stateless locator helpers for finding an {@see AgentModel} from a hosted
 * chat address (the `/chat/{agentSlug}` path segment or a custom domain) and
 * minting collision-free external ids. A deleted agent is never found (the
 * soft-delete scope), so its chat answers 404 at once. Named distinctly from
 * the `Agent` model so call sites never have to alias one of them away.
 */
class AgentLocator
{
    /**
     * The agent whose slug or external id is $slug (the `/chat/{agentSlug}`
     * segment). An empty slug matches nothing: a NULL lookup would match
     * agents without a slug.
     */
    public static function resolveAgentBySlug(string $slug): ?AgentModel
    {
        if ($slug === '') {
            return null;
        }

        return AgentModel::query()
            ->where('slug', $slug)
            ->orWhere('external_id', $slug)
            ->first();
    }

    /**
     * The agent that claimed $host as its custom domain, or null for hosts
     * that aren't bound to any agent.
     *
     * No caching: a single indexed lookup, like `resolveAgentBySlug`.
     */
    public static function resolveByHost(string $host): ?AgentModel
    {
        $host = Str::lower(Str::trim($host));

        if ($host === '') {
            return null;
        }

        return AgentModel::query()
            ->where('domain', $host)
            ->first();
    }

    /**
     * A deleted agent keeps its external id until its purge, and the column is
     * unique, so deleted agents count as taken.
     */
    public static function generateRandomExternalId(int $len = 14): string
    {
        do {
            $externalId = substr(bin2hex(random_bytes((int) ceil($len / 2))), 0, $len);
        } while (AgentModel::withTrashed()->where('external_id', $externalId)->exists());

        return $externalId;
    }
}
