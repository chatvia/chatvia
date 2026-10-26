<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Support\License\Entitlements;

/**
 * Whether a workspace may create one more agent. Without a license key a
 * workspace holds ten (a free-use limit; deleted agents do not count).
 * Agents made while the install had a key keep working after it lapses;
 * only new ones are refused.
 */
class AgentAllowance
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function canCreate(Workspace $workspace): bool
    {
        $limit = $this->limit();

        return $limit === null || $this->count($workspace) < $limit;
    }

    /** The most agents a workspace may hold, or null for no limit. */
    public function limit(): ?int
    {
        return $this->entitlements->agentLimit();
    }

    /** The workspace's agents, deleted ones left out. */
    public function count(Workspace $workspace): int
    {
        return Agent::query()->where('workspace_id', $workspace->id)->count();
    }
}
